<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function is_string;
use function preg_match;

/** @internal */
final class CollectionTypeResolver
{
    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolveListItem(ReflectionParameter|ReflectionProperty $field): string|null
    {
        return self::resolveDeclaration($field, 'list\\s*<\\s*(?<type>\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\\s*>');
    }

    /**
     * @return 'bool'|'float'|'int'|'string'|class-string|null
     */
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

    /** @return non-empty-string */
    private static function declarationPattern(
        ReflectionParameter|ReflectionProperty $field,
        string $declarationPattern,
    ): string {
        $fieldPattern = $field instanceof ReflectionParameter
            ? '\\s+\\$' . $field->getName() . '(?:\\s|$)'
            : '(?:\\s|$)';

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
