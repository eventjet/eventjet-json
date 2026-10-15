<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\ValueTypeMatcher;
use JsonException;
use Override;

use function is_float;
use function is_int;

/** @internal */
final readonly class ScalarNode implements Node
{
    private string $expected;

    public function __construct(
        public string $name,
        public bool $nullable,
    ) {
        $this->expected = $nullable ? FieldTypeNameResolver::withNull($name) : $name;
    }

    /** @return array<array-key, mixed>|bool|float|int|object|string|null */
    #[Override]
    public function decode(mixed $value, string $path): array|bool|float|int|object|string|null
    {
        $matches = ValueTypeMatcher::matches($value, $this->name, $this->nullable);
        if (!$matches) {
            return InvalidValue::typeMismatch($path, $this->expected, $value);
        }
        /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
        return $this->name === 'float' && is_int($value) ? (float) $value : $value;
    }

    /**
     * Decodes collection items of type bool, int, string, or float without building a path for each valid item.
     *
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @param Closure(TKey): string $path
     * @return array<TKey, mixed>|DecodeError
     * @throws JsonException
     */
    public function decodeEach(array $values, Closure $path): array|DecodeError
    {
        if ($this->name === 'float') {
            return $this->floats($values, $path);
        }
        $invalid = ScalarItems::firstInvalid($values, $this->name);
        return $invalid === null
            ? $values
            : InvalidValue::typeMismatch($path($invalid), $this->name, $values[$invalid] ?? null);
    }

    /**
     * Checks and converts float items in one pass, because JSON encodes whole floats as integers.
     *
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @param Closure(TKey): string $path
     * @return array<TKey, mixed>|DecodeError
     */
    private function floats(array $values, Closure $path): array|DecodeError
    {
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (is_float($value)) {
                continue;
            }
            if (!is_int($value)) {
                return InvalidValue::typeMismatch($path($key), 'float', $value);
            }
            $values[$key] = (float) $value;
        }
        return $values;
    }

    #[Override]
    public function valueTypes(): array
    {
        return $this->name === 'bool' ? ['true', 'false'] : [$this->name];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
