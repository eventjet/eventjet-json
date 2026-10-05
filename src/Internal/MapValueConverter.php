<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use UnitEnum;

use function array_key_exists;
use function array_keys;
use function assert;
use function class_exists;
use function enum_exists;
use function in_array;
use function is_bool;
use function is_float;
use function is_string;
use function sprintf;

/** @internal */
final class MapValueConverter
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): array|DecodeError {
        $values = MapInputNormalizer::normalize($class, $field, $value);

        if ($values instanceof DecodeError) {
            return $values;
        }

        $valueType = MapTypeResolver::resolveValue($field);

        assert($valueType !== null, description: 'Map declarations are validated before conversion.');

        if (enum_exists($valueType)) {
            return self::convertEnumValues($class, $field, $valueType, $values);
        }

        if (class_exists($valueType)) {
            return ConcreteClassMapValueConverter::convert($class, $field, $valueType, $values);
        }

        assert(
            in_array($valueType, ['bool', 'float', 'int', 'string'], strict: true),
            description: 'Map value types are validated before conversion.',
        );

        return self::convertScalarValues($class, $field, $valueType, $values);
    }

    /**
     * @param class-string $class
     * @param enum-string $type
     * @param array<array-key, mixed> $value
     * @return array<array-key, UnitEnum>|DecodeError
     * @throws ReflectionException
     */
    private static function convertEnumValues(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
        array $value,
    ): array|DecodeError {
        $enum = new ReflectionEnum($type);

        if (!$enum->isBacked()) {
            return DecodeError::nonBackedEnum($class, $type, $field->getName());
        }

        $converted = [];

        foreach (array_keys($value) as $key) {
            assert(array_key_exists($key, $value), description: 'A key returned by array_keys() must exist.');
            $path = sprintf('%s[%s]', $field->getName(), $key);
            $convertedValue = BackedEnumValueConverter::convertValue($class, $path, $type, $value[$key]);

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
     * @param array<array-key, mixed> $value
     * @return array<array-key, bool|float|int|string>|DecodeError
     */
    private static function convertScalarValues(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
        array $value,
    ): array|DecodeError {
        $converted = [];

        foreach (array_keys($value) as $key) {
            assert(array_key_exists($key, $value), description: 'A key returned by array_keys() must exist.');
            $path = sprintf('%s[%s]', $field->getName(), $key);
            $convertedValue = self::convertScalar($class, $path, $type, $value[$key]);

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
