<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final class MapTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
    ): DecodeError|null {
        return match ($type) {
            'array' => self::validateArray($class, $field),
            ArrayObject::class => self::validateArrayObject($class, $field),
            default => null,
        };
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateArray(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): DecodeError|null {
        $hasAmbiguousArray = MapTypeResolver::hasAmbiguousArray($field);

        if ($hasAmbiguousArray) {
            return MapDecodeError::ambiguousDeclaration($class, $field->getName());
        }

        $hasUnsupportedNonEmptyKey = MapTypeResolver::hasUnsupportedNonEmptyKey($field);

        if ($hasUnsupportedNonEmptyKey) {
            return MapDecodeError::unsupportedDeclaration($class, $field->getName(), 'non-empty-array');
        }

        return self::validateValue($class, $field);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateArrayObject(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): DecodeError|null {
        $isArrayObject = MapTypeResolver::isArrayObject($field);

        if (!$isArrayObject) {
            return MapDecodeError::unsupportedDeclaration($class, $field->getName(), ArrayObject::class);
        }

        return self::validateValue($class, $field);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateValue(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): DecodeError|null {
        $value = MapTypeResolver::resolveValue($field);

        return $value === null ? null : ClassFieldTypeValidator::validate($class, $field->getName(), $value);
    }
}
