<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function strcasecmp;

/** @internal */
final class MapTypeResolver
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType|null $type,
        string $nativeType,
    ): MapType|DecodeError|null {
        $arrayObject = $nativeType === ArrayObject::class;
        if ($type === null || count($type->arguments) < 2) {
            return $arrayObject
                ? MapDecodeError::unsupportedDeclaration($class, $field->getName(), ArrayObject::class)
                : null;
        }
        $arguments = $type->arguments;
        $key = $arguments[0];
        $hasStringKey = $key->name === 'string' && $key->arguments === [];

        if ($arrayObject) {
            $container = FieldTypeNameResolver::resolvePhpDoc($field, $type->name);
            if (!$hasStringKey || strcasecmp($container ?? $type->name, ArrayObject::class) !== 0) {
                return MapDecodeError::unsupportedDeclaration($class, $field->getName(), ArrayObject::class);
            }
            return self::map($field, $arguments, $arrayObject);
        }
        if ($type->name === 'array') {
            return MapDecodeError::ambiguousDeclaration($class, $field->getName());
        }
        if ($type->name !== 'non-empty-array') {
            return null;
        }
        if (!$hasStringKey) {
            return MapDecodeError::unsupportedDeclaration($class, $field->getName(), 'non-empty-array');
        }
        return self::map($field, $arguments, $arrayObject);
    }

    /**
     * @param list<PhpDocType> $arguments
     * @throws ReflectionException
     */
    private static function map(
        ReflectionParameter|ReflectionProperty $field,
        array $arguments,
        bool $arrayObject,
    ): MapType|null {
        $valueType = $arguments[1] ?? null;
        if ($valueType === null || count($arguments) !== 2) {
            return null;
        }
        $value = PhpDocItemTypeResolver::resolve($field, $valueType);
        return $value === null ? null : new MapType($value, $arrayObject);
    }
}
