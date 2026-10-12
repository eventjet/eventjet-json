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
use function interface_exists;
use function strcasecmp;

/** @internal */
final class FieldTypeNameResolver
{
    private const array PRIMITIVE_PHPDOC = [
        'non-empty-string' => 'string',
        'numeric-string' => 'string',
        'literal-string' => 'string',
        'positive-int' => 'int',
        'negative-int' => 'int',
        'non-positive-int' => 'int',
        'non-negative-int' => 'int',
        'non-zero-int' => 'int',
        'bool' => 'bool',
        'float' => 'float',
        'int' => 'int',
        'string' => 'string',
    ];

    public static function resolve(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): string
    {
        $name = $type->getName();
        if ($name === 'self') {
            /** @var ReflectionClass<object> $declaringClass */
            $declaringClass = $field->getDeclaringClass();
            return $declaringClass->getName();
        }

        if ($name !== 'parent') {
            return strcasecmp($name, ArrayObject::class) === 0 ? ArrayObject::class : $name;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();
        $parent = $declaringClass->getParentClass();

        return $parent === false ? $name : $parent->getName();
    }

    /** Returns the doc comment that declares a field: the constructor's for a parameter. */
    public static function docComment(ReflectionParameter|ReflectionProperty $field): string
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        return (string) $doc;
    }

    /** Returns the type that errors report for a nullable declaration of a type. */
    public static function withNull(string $name): string
    {
        return $name === 'null' ? $name : $name . '|null';
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        $primitive = self::PRIMITIVE_PHPDOC[$type] ?? null;
        if ($primitive !== null) {
            return $primitive;
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
