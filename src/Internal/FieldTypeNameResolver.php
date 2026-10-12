<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function strcasecmp;
use function strpbrk;
use function strtr;

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

    /**
     * Whether the native type check is the whole check of a field: a scalar type without a PHPDoc literal. Skipping the
     * type compiler for such fields keeps it unloaded for classes with only scalar fields.
     */
    public static function isPlainType(ReflectionParameter|ReflectionProperty $field, ReflectionType|null $type): bool
    {
        return (
            $type instanceof ReflectionNamedType
            && $type->isBuiltin()
            && !in_array($type->getName(), ['array', 'mixed', 'object'], strict: true)
            && !self::mayDeclareLiteral(self::docComment($field))
        );
    }

    /** Whether a doc comment may declare a literal type. False means it certainly does not. */
    public static function mayDeclareLiteral(string $docComment): bool
    {
        $markers = strtr($docComment, ['true' => '"', 'false' => '"', ' null' => '"', "\tnull" => '"']);
        return strpbrk($markers, characters: "'\"0123456789:") !== false;
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
