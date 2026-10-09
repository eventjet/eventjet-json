<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
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

    public static function literalMarkerDocComment(string|false $docComment): string|false
    {
        if ($docComment === false) {
            return false;
        }
        $mayContainLiteralMarker = self::mayContainLiteralMarker($docComment);
        return $mayContainLiteralMarker ? $docComment : false;
    }

    /** @throws ReflectionException */
    public static function hasLiteralPhpDoc(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection = null,
        string|false|null $docComment = null,
    ): bool {
        if ($collection !== null || $docComment === false) {
            return false;
        }
        // Constructor parameters share a docblock; the caller checks it once
        // before creating each parameter's metadata.
        $mayContainLiteral =
            $field instanceof ReflectionParameter || $docComment !== null && self::mayContainLiteralMarker($docComment);
        if (!$mayContainLiteral) {
            return false;
        }
        return PhpDocLiteralFieldCache::hasLiteral($field, $docComment);
    }

    public static function mayContainLiteralMarker(string $docComment): bool
    {
        return PhpDocLiteralFieldMarker::mayContainLiteralMarker($docComment);
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

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        $primitive = self::primitivePhpDoc($type);
        if ($primitive !== null) {
            return $primitive;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        $resolvedType = PhpDocClassNameResolver::resolve($declaringClass, $type);

        return class_exists($resolvedType) || interface_exists($resolvedType) ? $resolvedType : null;
    }

    /**
     * @pure
     * @return 'bool'|'float'|'int'|'string'|null
     */
    private static function primitivePhpDoc(string $type): string|null
    {
        return self::PRIMITIVE_PHPDOC[$type] ?? null;
    }
}
