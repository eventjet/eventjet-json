<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

/** @internal */
final class ConcreteClassValueConverter
{
    /**
     * @param class-string $class
     * @param class-string $typeName
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $typeName,
        mixed $value,
    ): object|null {
        $allowsNull = (bool) $field->getType()?->allowsNull();

        if ($value === null && $allowsNull) {
            return null;
        }

        $expectedType = $typeName;

        if ($allowsNull) {
            $expectedType .= '|null';
        }

        return self::convertValue($class, $field->getName(), $typeName, $expectedType, $value);
    }

    /**
     * @param class-string $class
     * @param class-string $typeName
     */
    public static function convertCollectionItem(string $class, string $path, string $typeName, mixed $value): object
    {
        return self::convertValue($class, $path, $typeName, $typeName, $value);
    }

    /**
     * @param class-string $class
     * @param class-string $typeName
     */
    private static function convertValue(
        string $class,
        string $path,
        string $typeName,
        string $expectedType,
        mixed $value,
    ): object {
        return $value instanceof stdClass
            ? ObjectHydrator::hydrate($typeName, $value)
            : DecodeError::fieldTypeMismatch($class, $path, $expectedType, $value);
    }
}
