<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\ValueTypeMatcher;
use JsonException;
use Override;

use function is_float;
use function is_int;

/** @internal */
final readonly class ScalarNode implements ItemNode
{
    private string $expected;

    /** @param class-string $class */
    public function __construct(
        private string $class,
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
            return DecodeError::fieldTypeMismatch($this->class, $path, $this->expected, $value);
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
        $invalid = ScalarItems::firstInvalid($values, $this->name);
        if ($invalid !== null) {
            return DecodeError::fieldTypeMismatch(
                $this->class,
                $path($invalid),
                $this->name,
                $values[$invalid] ?? null,
            );
        }
        /** @var array<TKey, float|int> $values */
        return $this->name === 'float' ? self::floats($values) : $values;
    }

    /**
     * @template TKey of array-key
     * @param array<TKey, float|int> $values
     * @return array<TKey, float>
     */
    private static function floats(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_float($value)) {
                continue;
            }
            $values[$key] = (float) $value;
        }
        return $values;
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
