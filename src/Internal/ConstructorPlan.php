<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use stdClass;

use function array_all;
use function array_intersect_key;
use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final class ConstructorPlan
{
    public readonly bool $scalarOnly;

    /**
     * @var (Closure(stdClass): (object|null))|false|null
     * @psalm-suppress UnusedProperty Psalm does not track references from the hydrate property hook.
     */
    private Closure|false|null $compiledHydrator = false;

    /** @var (Closure(stdClass): (object|null))|null */
    public Closure|null $hydrate {
        get => $this->compiledHydrator === false
            ? ($this->compiledHydrator = ScalarHydratorCompiler::compile($this->class))
            : $this->compiledHydrator;
    }

    /**
     * @param class-string $class
     * @param array<string, ConstructorValueValidator> $fields
     * @param array<string, FieldValueConverter|null> $converters
     * @throws ReflectionException
     */
    public function __construct(
        private readonly string $class,
        private readonly array $fields,
        private readonly array $converters,
    ) {
        $this->scalarOnly = array_all(
            $converters,
            static fn(FieldValueConverter|null $converter): bool => $converter === null,
        );
    }

    /**
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public function decode(array $values, string $path): array|DecodeError
    {
        foreach ($this->fields as $name => $field) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            $matches = $value === null
                ? $field->nullable
                : match ($field->typeName) {
                    'array' => is_array($value) || $value instanceof stdClass,
                    'bool' => is_bool($value),
                    'false' => $value === false,
                    'float' => is_float($value) || is_int($value),
                    'int' => is_int($value),
                    'null' => false,
                    'string' => is_string($value),
                    'true' => $value === true,
                    default => true,
                };
            if (!$matches) {
                return DecodeError::fieldTypeMismatch(
                    $this->class,
                    FieldPath::field($path, $name),
                    $field->expected,
                    $value,
                );
            }
        }
        return $this->scalarOnly ? array_intersect_key($values, $this->fields) : $this->convert($values, $path);
    }

    /**
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public function convert(array $values, string $path): array|DecodeError
    {
        $converted = [];
        foreach ($this->converters as $name => $converter) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            if ($converter !== null) {
                $value = $converter->convert($this->class, $value, FieldPath::field($path, $name));
                if ($value instanceof DecodeError) {
                    return $value;
                }
            }
            $converted[$name] = $value;
        }
        return $converted;
    }
}
