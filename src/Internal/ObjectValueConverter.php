<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;

use function array_key_exists;
use function array_map;
use function class_exists;
use function enum_exists;
use function get_object_vars;
use function is_array;

/** @internal */
final class ObjectValueConverter
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(ReflectionClass $class, array $values): array|DecodeError
    {
        $className = $class->getName();
        $convertedValues = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $parameter->getName();

            if (!array_key_exists($field, $values)) {
                continue;
            }

            /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
            $value = $values[$field];
            $converted = self::convertField($className, $parameter, $value);

            if ($converted instanceof DecodeError) {
                return $converted;
            }

            $convertedValues[$field] = $converted;
        }

        return $convertedValues;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    private static function convertField(
        string $class,
        ReflectionParameter $parameter,
        mixed $value,
    ): array|bool|float|int|object|string|null {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            $typeName = FieldTypeNameResolver::resolve($parameter, $type);

            if (enum_exists($typeName)) {
                return BackedEnumValueConverter::convert($class, $parameter, $value) ?? $value;
            }

            if (class_exists($typeName)) {
                return ConcreteClassValueConverter::convert($class, $parameter, $typeName, $value);
            }
        }

        if ($type instanceof ReflectionUnionType) {
            $converted = BackedEnumValueConverter::convert($class, $parameter, $value);

            if ($converted !== null) {
                return $converted;
            }

            if ($value instanceof stdClass) {
                $converted = ConcreteClassUnionValueConverter::convert($class, $parameter, $type, $value);

                if ($converted !== null) {
                    return $converted;
                }
            }
        }

        return self::convertObjectsToArrays($value);
    }

    /**
     * @param array<array-key, mixed>|stdClass $value
     * @return array<array-key, mixed>
     */
    public static function convertArrayValue(array|stdClass $value): array
    {
        return array_map(
            self::convertObjectsToArrays(...),
            $value instanceof stdClass ? get_object_vars($value) : $value,
        );
    }

    /** @return array<array-key, mixed>|bool|float|int|object|string|null */
    private static function convertObjectsToArrays(mixed $value): array|bool|float|int|object|string|null
    {
        if ($value instanceof stdClass || is_array($value)) {
            return self::convertArrayValue($value);
        }

        /** @var bool|float|int|object|string|null $value */
        return $value;
    }
}
