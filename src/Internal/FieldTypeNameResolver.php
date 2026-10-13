<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function in_array;
use function str_contains;
use function strcasecmp;

/** @internal */
final class FieldTypeNameResolver
{
    /** @var array<string, array<string, PhpDocType>> Keyed by tag and doc comment, then by parameter name. */
    private static array $phpDocs = [];

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

    /**
     * Returns the PHPDoc type of a field: its `@var` tag, or the constructor's `@param` tag for a parameter. Each doc
     * comment is parsed once, even if it declares several parameters, and only if it has the tag.
     */
    public static function phpDoc(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        [$doc, $tag, $name] = $field instanceof ReflectionParameter
            ? [$field->getDeclaringFunction()->getDocComment(), '@param', $field->getName()]
            : [$field->getDocComment(), '@var', ''];
        $doc = (string) $doc;
        self::$phpDocs[$tag . $doc] ??= str_contains($doc, $tag) ? PhpDocFieldType::types($doc, $tag) : [];
        return self::$phpDocs[$tag . $doc][$name] ?? null;
    }

    /**
     * Returns the PHPDoc type of a field whose native type a PHPDoc literal can restrict: a scalar, a backed enum, or a
     * union without collections. Collection items declare their literals in the collection type.
     */
    public static function literalDeclaration(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
    ): PhpDocType|null {
        $accepts = $type instanceof ReflectionUnionType
            ? !NativeUnionCompiler::hasCollection($type)
            : $type instanceof ReflectionNamedType
            && ($type->isBuiltin() ? $type->getName() !== 'array' : enum_exists($type->getName()));
        return $accepts ? self::phpDoc($field) : null;
    }

    /**
     * Whether the native type check is the whole check of a field, so the field needs no compilation: its type is a
     * builtin that the type compiler treats as a scalar, and no PHPDoc tag declares a type for it.
     */
    public static function nativeCheckIsComplete(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
    ): bool {
        return (
            $type instanceof ReflectionNamedType
            && $type->isBuiltin()
            && !in_array($type->getName(), ['array', 'mixed', 'object'], strict: true)
            && self::phpDoc($field) === null
        );
    }

    /** Returns the type that errors report for a nullable declaration of a type. */
    public static function withNull(string $name): string
    {
        return $name === 'null' ? $name : $name . '|null';
    }
}
