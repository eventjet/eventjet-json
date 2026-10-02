<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function class_exists;
use function enum_exists;

/** @internal */
final class PublicPropertyValueConverter
{
    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws ReflectionException
     */
    public static function convert(string $class, ReflectionProperty $property, mixed $value): array|DecodeError
    {
        $field = $property->getName();
        $type = PublicPropertyTypeValidator::validate($class, $property);

        if ($type instanceof DecodeError) {
            return $type;
        }

        if ($type instanceof ReflectionUnionType) {
            return PublicPropertyUnionValueConverter::convert($class, $property, $type, $value);
        }

        $typeName = FieldTypeNameResolver::resolve($property, $type);

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

            return DecodeError::fieldTypeMismatch($class, $field, $expectedType, $value);
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
            $converted = BackedEnumMapValueConverter::convert($class, $property, $value);
        }

        if ($converted instanceof DecodeError) {
            return $converted;
        }

        return [
            'property' => $property,
            'value' => $converted ?? ObjectValueConverter::convertArrayValue($value),
        ];
    }
}
