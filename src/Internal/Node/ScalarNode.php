<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use JsonException;
use Override;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Accepts values of type bool, int, float, or string.
 *
 * @internal
 */
final readonly class ScalarNode implements Node
{
    private string $expected;

    public function __construct(
        public string $name,
        public bool $nullable,
    ) {
        $this->expected = $nullable ? FieldTypeNameResolver::withNull($name) : $name;
    }

    /**
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws DecodeError
     */
    #[Override]
    public function decode(mixed $value, string $path): array|bool|float|int|object|string|null
    {
        $matches = $value === null
            ? $this->nullable
            : match ($this->name) {
                'bool' => is_bool($value),
                'float' => is_float($value) || is_int($value),
                'int' => is_int($value),
                default => is_string($value),
            };
        if (!$matches) {
            throw InvalidValue::typeMismatch($path, $this->expected, $value);
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
     * @return array<TKey, mixed>
     * @throws DecodeError
     * @throws JsonException
     */
    public function decodeEach(array $values, Closure $path): array
    {
        if ($this->name === 'float') {
            return $this->floats($values, $path);
        }
        $invalid = ScalarItems::firstInvalid($values, $this->name);
        if ($invalid !== null) {
            throw InvalidValue::typeMismatch($path($invalid), $this->name, $values[$invalid] ?? null);
        }
        return $values;
    }

    /**
     * Checks and converts float items in one pass, because JSON encodes whole floats as integers.
     *
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @param Closure(TKey): string $path
     * @return array<TKey, mixed>
     * @throws DecodeError
     */
    private function floats(array $values, Closure $path): array
    {
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (is_float($value)) {
                continue;
            }
            if (!is_int($value)) {
                throw InvalidValue::typeMismatch($path($key), 'float', $value);
            }
            $values[$key] = (float) $value;
        }
        return $values;
    }

    /** A whole float may arrive as a JSON integer, so `float` takes integers unless `int` accepts them. */
    #[Override]
    public function claims(): array
    {
        $accepts = ['others' => 'accepts', 'enums' => [], 'values' => []];
        return match ($this->name) {
            'bool' => ['true' => $accepts, 'false' => $accepts],
            'float' => ['int' => ['others' => 'widens', 'enums' => [], 'values' => []], 'float' => $accepts],
            default => [$this->name => $accepts],
        };
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
