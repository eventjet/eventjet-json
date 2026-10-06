<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
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
        string $path,
    ): array|DecodeError {
        $converted = self::convertBackedEnum($class, $property, $value, $path);

        if ($converted !== null) {
            return $converted;
        }

        $converted = self::convertConcreteClass($class, $property, $type, $value, $path);

        if ($converted !== null) {
            return $converted;
        }

        return self::convertBuiltin($class, $property, $type, $value, $path);
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
        string $path,
    ): array|DecodeError|null {
        $converted = BackedEnumValueConverter::convert($class, $property, $value, $path);

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
        string $path,
    ): array|DecodeError|null {
        if (!$value instanceof stdClass) {
            return null;
        }

        $converted = ConcreteClassUnionValueConverter::convert($class, $property, $type, $value, $path);

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
        string $path,
    ): array|DecodeError {
        $matches = ValueTypeMatcher::matchesBuiltinUnion($value, $type);

        if (!$matches) {
            return DecodeError::fieldTypeMismatch($class, $path, (string) $type, $value);
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
}
