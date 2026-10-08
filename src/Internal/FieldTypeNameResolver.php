<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function strcasecmp;

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
            return strcasecmp($name, ArrayObject::class) === 0 ? ArrayObject::class : $name;
        }

        $parent = $declaringClass->getParentClass();

        return $parent === false ? $name : $parent->getName();
    }

    public static function expected(ReflectionNamedType $type, string $name): string
    {
        return $type->allowsNull() && $name !== 'null' ? $name . '|null' : $name;
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        if ($type === 'non-empty-string' || $type === 'numeric-string' || $type === 'literal-string') {
            return 'string';
        }

        $isRefinedInteger = in_array(
            $type,
            ['positive-int', 'negative-int', 'non-positive-int', 'non-negative-int', 'non-zero-int'],
            strict: true,
        );

        if ($isRefinedInteger) {
            return 'int';
        }

        if (in_array($type, ['bool', 'float', 'int', 'string'], strict: true)) {
            return $type;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        if ($type === 'self') {
            return $declaringClass->getName();
        }

        $resolvedType = PhpDocClassNameResolver::resolve($declaringClass, $type);

        return enum_exists($resolvedType) || class_exists($resolvedType) || interface_exists($resolvedType)
            ? $resolvedType
            : null;
    }
}
