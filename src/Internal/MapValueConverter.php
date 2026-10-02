<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;
use UnitEnum;

use function array_key_exists;
use function array_keys;
use function assert;
use function class_exists;
use function enum_exists;
use function get_object_vars;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/** @internal */
final class MapValueConverter
{
    /**
     * @param class-string $class
     * @param array<array-key, mixed>|stdClass $value
     * @return array<array-key, bool|float|int|object|string|UnitEnum>|DecodeError|null
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        array|stdClass $value,
    ): array|DecodeError|null {
        $valueType = CollectionTypeResolver::resolveMapValue($field);

        if ($valueType === null) {
            return null;
        }

        if (enum_exists($valueType)) {
            return self::convertEnumValues($class, $field, $valueType, $value);
        }

        if (class_exists($valueType)) {
            return ConcreteClassMapValueConverter::convert($class, $field, $valueType, $value);
        }

        return match ($valueType) {
            'bool', 'float', 'int', 'string' => self::convertScalarValues($class, $field, $valueType, $value),
            default => null,
        };
    }

    /**
     * @param class-string $class
     * @param enum-string $type
     * @param array<array-key, mixed>|stdClass $value
     * @return array<array-key, UnitEnum>|DecodeError
     * @throws ReflectionException
     */
    private static function convertEnumValues(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
        array|stdClass $value,
    ): array|DecodeError {
        $enum = new ReflectionEnum($type);

        if (!$enum->isBacked()) {
            return DecodeError::nonBackedEnum($class, $type, $field->getName());
        }

        $values = $value instanceof stdClass ? get_object_vars($value) : $value;
        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $path = sprintf('%s[%s]', $field->getName(), $key);
            $convertedValue = BackedEnumValueConverter::convertValue($class, $path, $type, $values[$key]);

            if ($convertedValue instanceof DecodeError) {
                return $convertedValue;
            }

            $converted[$key] = $convertedValue;
        }

        return $converted;
    }

    /**
     * @param class-string $class
     * @param 'bool'|'float'|'int'|'string' $type
     * @param array<array-key, mixed>|stdClass $value
     * @return array<array-key, bool|float|int|string>|DecodeError
     */
    private static function convertScalarValues(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
        array|stdClass $value,
    ): array|DecodeError {
        $values = $value instanceof stdClass ? get_object_vars($value) : $value;
        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $path = sprintf('%s[%s]', $field->getName(), $key);
            $convertedValue = self::convertScalar($class, $path, $type, $values[$key]);

            if ($convertedValue instanceof DecodeError) {
                return $convertedValue;
            }

            $converted[$key] = $convertedValue;
        }

        return $converted;
    }

    /**
     * @param class-string $class
     * @param 'bool'|'float'|'int'|'string' $type
     */
    private static function convertScalar(
        string $class,
        string $path,
        string $type,
        mixed $value,
    ): bool|float|int|string|DecodeError {
        return match ($type) {
            'bool' => is_bool($value) ? $value : DecodeError::fieldTypeMismatch($class, $path, $type, $value),
            'float' => is_float($value) || is_int($value)
                ? (float) $value
                : DecodeError::fieldTypeMismatch($class, $path, $type, $value),
            'int' => is_int($value) ? $value : DecodeError::fieldTypeMismatch($class, $path, $type, $value),
            'string' => is_string($value) ? $value : DecodeError::fieldTypeMismatch($class, $path, $type, $value),
        };
    }
}
