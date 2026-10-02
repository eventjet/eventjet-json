<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

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
}
