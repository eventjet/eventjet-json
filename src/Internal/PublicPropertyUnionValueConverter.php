<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

/** @internal */
final class PublicPropertyUnionValueConverter
{
    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionProperty $property,
        ReflectionUnionType $type,
        mixed $value,
    ): array|DecodeError {
        $converted = self::convertBackedEnum($class, $property, $value);

        if ($converted !== null) {
            return $converted;
        }

        $converted = self::convertConcreteClass($class, $property, $type, $value);

        if ($converted !== null) {
            return $converted;
        }

        return self::convertBuiltin($class, $property, $type, $value);
    }

    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError|null
     * @throws ReflectionException
     */
    private static function convertBackedEnum(
        string $class,
        ReflectionProperty $property,
        mixed $value,
    ): array|DecodeError|null {
        $converted = BackedEnumValueConverter::convert($class, $property, $value);

        return self::assignment($property, $converted);
    }

    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError|null
     */
    private static function convertConcreteClass(
        string $class,
        ReflectionProperty $property,
        ReflectionUnionType $type,
        mixed $value,
    ): array|DecodeError|null {
        if (!$value instanceof stdClass) {
            return null;
        }

        $converted = ConcreteClassUnionValueConverter::convert($class, $property, $type, $value);

        return self::assignment($property, $converted);
    }

    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     */
    private static function convertBuiltin(
        string $class,
        ReflectionProperty $property,
        ReflectionUnionType $type,
        mixed $value,
    ): array|DecodeError {
        $matchingType = self::matchingBuiltinType($type, $value);

        if (!$matchingType instanceof ReflectionNamedType) {
            return DecodeError::fieldTypeMismatch($class, $property->getName(), (string) $type, $value);
        }

        return ['property' => $property, 'value' => $value];
    }

    /** @return array{property: ReflectionProperty, value: object}|DecodeError|null */
    private static function assignment(ReflectionProperty $property, object|null $value): array|DecodeError|null
    {
        if ($value instanceof DecodeError || $value === null) {
            return $value;
        }

        return ['property' => $property, 'value' => $value];
    }

    private static function matchingBuiltinType(ReflectionUnionType $type, mixed $value): ReflectionNamedType|null
    {
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType || !$member->isBuiltin()) {
                continue;
            }

            $valueMatchesType = ValueTypeMatcher::matches($value, $member);

            if ($valueMatchesType) {
                return $member;
            }
        }

        return null;
    }
}
