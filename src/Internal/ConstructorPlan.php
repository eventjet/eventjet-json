<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use stdClass;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final readonly class ConstructorPlan
{
    /**
     * @param class-string $class
     * @param array<array-key, ConstructorValueValidator> $fields
     * @param array<array-key, FieldValueConverter|null> $converters
     */
    public function __construct(
        private string $class,
        private array $fields,
        private array $converters,
        public bool $cacheable,
    ) {}

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     */
    public function validate(array $values, string $path): DecodeError|null
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
                    FieldPath::field($path, (string) $name),
                    $field->expected,
                    $value,
                );
            }
        }
        return null;
    }

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
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
                $value = $converter->convert($this->class, $value, FieldPath::field($path, (string) $name));
                if ($value instanceof DecodeError) {
                    return $value;
                }
            }
            $converted[$name] = $value;
        }
        return $converted;
    }
}
