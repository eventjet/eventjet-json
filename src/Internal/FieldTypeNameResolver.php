<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function ltrim;
use function str_starts_with;

/** @internal */
final class FieldTypeNameResolver
{
    public static function resolve(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): string
    {
        $name = $type->getName();
        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        if ($name === 'self') {
            return $declaringClass->getName();
        }

        if ($name !== 'parent') {
            return $name;
        }

        $parent = $declaringClass->getParentClass();

        return $parent === false ? $name : $parent->getName();
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        if (in_array($type, ['bool', 'float', 'int', 'string'], strict: true)) {
            return $type;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();
        $resolvedType = str_starts_with($type, '\\')
            ? ltrim($type, characters: '\\')
            : $declaringClass->getNamespaceName() . '\\' . $type;

        return enum_exists($resolvedType) || class_exists($resolvedType) || interface_exists($resolvedType)
            ? $resolvedType
            : null;
    }
}
