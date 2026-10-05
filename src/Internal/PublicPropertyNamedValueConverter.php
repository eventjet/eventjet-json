<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;

use function class_exists;
use function enum_exists;

/** @internal */
final class PublicPropertyNamedValueConverter
{
    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionProperty $property,
        ReflectionNamedType $type,
        mixed $value,
    ): array|DecodeError {
        $typeName = FieldTypeNameResolver::resolve($property, $type);

        if ($typeName === ArrayObject::class) {
            $converted = ArrayObjectMapValueConverter::convert($class, $property, $value);

            return $converted instanceof DecodeError ? $converted : ['property' => $property, 'value' => $converted];
        }

        if (enum_exists($typeName)) {
            $converted = BackedEnumValueConverter::convert($class, $property, $value);

            return (
                $converted instanceof DecodeError
                    ? $converted
                    : ['property' => $property, 'value' => $converted ?? $value]
            );
        }

        if (class_exists($typeName)) {
            $converted = ConcreteClassValueConverter::convert($class, $property, $typeName, $value);

            return $converted instanceof DecodeError ? $converted : ['property' => $property, 'value' => $converted];
        }

        $valueMatchesType = ValueTypeMatcher::matches($value, $type);

        if (!$valueMatchesType) {
            $expectedType = $typeName;

            if ($type->allowsNull() && $expectedType !== 'null') {
                $expectedType .= '|null';
            }

            return DecodeError::fieldTypeMismatch($class, $property->getName(), $expectedType, $value);
        }

        if ($typeName === 'array') {
            /** @var array<array-key, mixed>|stdClass $value */
            return self::convertArray($class, $property, $value);
        }

        return ['property' => $property, 'value' => $value];
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|stdClass $value
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws ReflectionException
     */
    private static function convertArray(
        string $class,
        ReflectionProperty $property,
        array|stdClass $value,
    ): array|DecodeError {
        $converted = ListValueConverter::convert($class, $property, $value);

        if ($converted === null) {
            $converted = TupleValueConverter::convert($class, $property, $value) ?? MapValueConverter::convert(
                $class,
                $property,
                $value,
            );
        }

        if ($converted instanceof DecodeError) {
            return $converted;
        }

        return ['property' => $property, 'value' => $converted];
    }
}
