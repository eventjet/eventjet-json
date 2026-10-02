<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionProperty;
use stdClass;

use function enum_exists;
use function is_array;

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

        $typeName = $type->getName();

        if (enum_exists($typeName)) {
            $converted = BackedEnumValueConverter::convert($class, $property, $value);

            return (
                $converted instanceof DecodeError
                    ? $converted
                    : ['property' => $property, 'value' => $converted ?? $value]
            );
        }

        $valueMatchesType = ValueTypeMatcher::matches($value, $type);

        if (!$valueMatchesType) {
            $expectedType = $typeName;

            if ($type->allowsNull() && $expectedType !== 'null') {
                $expectedType .= '|null';
            }

            return DecodeError::fieldTypeMismatch($class, $field, $expectedType, $value);
        }

        return [
            'property' => $property,
            'value' =>
                $typeName === 'array' && ($value instanceof stdClass || is_array($value))
                    ? ObjectValueConverter::convertArrayValue($value)
                    : $value,
        ];
    }
}
