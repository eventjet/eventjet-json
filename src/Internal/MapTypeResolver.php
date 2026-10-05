<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function is_string;
use function preg_match;

/** @internal */
final class MapTypeResolver
{
    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolveValue(ReflectionParameter|ReflectionProperty $field): string|null
    {
        $type = $field->getType();
        $typeName = $type instanceof ReflectionNamedType ? FieldTypeNameResolver::resolve($field, $type) : null;
        $declaration = $typeName === ArrayObject::class ? '(?:\\\\?ArrayObject)' : 'non-empty-array';

        return self::resolveDeclaration(
            $field,
            $declaration
            . '\\s*<\\s*string\\s*,\\s*(?<type>'
            . FieldTypeNameResolver::COLLECTION_TYPE_PATTERN
            . ')\\s*>',
        );
    }

    public static function isNonEmptyArray(ReflectionParameter|ReflectionProperty $field): bool
    {
        return (
            self::hasNativeType($field, 'array')
            && self::matchesDeclaration($field, 'non-empty-array\\s*<\\s*string\\s*,.+>')
        );
    }

    public static function isArrayObject(ReflectionParameter|ReflectionProperty $field): bool
    {
        return (
            self::hasNativeType($field, ArrayObject::class)
            && self::matchesDeclaration($field, '(?:\\\\?ArrayObject)\\s*<\\s*string\\s*,.+>')
        );
    }

    public static function hasAmbiguousArray(ReflectionParameter|ReflectionProperty $field): bool
    {
        return (
            self::hasNativeType($field, 'array') && self::matchesDeclaration($field, '(?<!non-empty-)array\\s*<.+,.+>')
        );
    }

    public static function hasUnsupportedNonEmptyKey(ReflectionParameter|ReflectionProperty $field): bool
    {
        return (
            self::hasNativeType($field, 'array')
            && self::matchesDeclaration($field, 'non-empty-array\\s*<.+,.+>')
            && !self::isNonEmptyArray($field)
        );
    }

    private static function hasNativeType(ReflectionParameter|ReflectionProperty $field, string $expected): bool
    {
        $type = $field->getType();

        return $type instanceof ReflectionNamedType && FieldTypeNameResolver::resolve($field, $type) === $expected;
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    private static function resolveDeclaration(
        ReflectionParameter|ReflectionProperty $field,
        string $declarationPattern,
    ): string|null {
        $matches = [];
        $matched = preg_match(
            self::declarationPattern($field, $declarationPattern),
            self::docComment($field),
            $matches,
        );
        $type = $matched === 1 ? $matches['type'] ?? null : null;

        return is_string($type) ? FieldTypeNameResolver::resolvePhpDoc($field, $type) : null;
    }

    private static function matchesDeclaration(
        ReflectionParameter|ReflectionProperty $field,
        string $declarationPattern,
    ): bool {
        return preg_match(self::declarationPattern($field, $declarationPattern), self::docComment($field)) === 1;
    }

    /** @return non-empty-string */
    private static function declarationPattern(
        ReflectionParameter|ReflectionProperty $field,
        string $declarationPattern,
    ): string {
        $fieldPattern = $field instanceof ReflectionParameter
            ? '\\s+\\$' . $field->getName() . '(?:\\s|$)'
            : '(?!\\s*[|&<>\\[\\],?])(?:\\s|$)';

        return (
            '/@'
            . ($field instanceof ReflectionParameter ? 'param' : 'var')
            . '\\s+'
            . $declarationPattern
            . $fieldPattern
            . '/'
        );
    }

    private static function docComment(ReflectionParameter|ReflectionProperty $field): string
    {
        $docComment = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();

        return $docComment === false ? '' : $docComment;
    }
}
