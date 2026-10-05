<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/** @internal */
final class PublicPropertyValueConverter
{
    /**
     * @param class-string $class
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionProperty $property,
        ReflectionNamedType|ReflectionUnionType $type,
        mixed $value,
    ): array|DecodeError {
        if ($type instanceof ReflectionUnionType) {
            return PublicPropertyUnionValueConverter::convert($class, $property, $type, $value);
        }

        return PublicPropertyNamedValueConverter::convert($class, $property, $type, $value);
    }
}
