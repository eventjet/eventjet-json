<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function interface_exists;
use function strcasecmp;

/** @internal */
final class FieldTypeNameResolver
{
    public static function hasLiteralPhpDoc(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection = null,
    ): bool {
        if ($collection !== null) {
            return false;
        }
        return PhpDocLiteralFieldCache::hasLiteral($field);
    }

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

    public static function expected(ReflectionNamedType $type, string $name): string
    {
        return $type->allowsNull() && $name !== 'null' ? $name . '|null' : $name;
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        $primitive = self::primitivePhpDoc($type);
        if ($primitive !== null) {
            return $primitive;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        if ($type === 'self') {
            return $declaringClass->getName();
        }

        $resolvedType = PhpDocClassNameResolver::resolve($declaringClass, $type);

        return class_exists($resolvedType) || interface_exists($resolvedType) ? $resolvedType : null;
    }

    /**
     * @pure
     * @return 'bool'|'float'|'int'|'string'|null
     */
    public static function primitivePhpDoc(string $type): string|null
    {
        return (
            [
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
            ][$type] ?? null
        );
    }
}
