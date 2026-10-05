<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;

use function is_string;
use function preg_match;
use function sprintf;

/** @internal */
final class CollectionTypeResolver
{
    /** @param class-string $class */
    public static function invalidDeclaration(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s has a missing or unrecognized collection declaration. Use @%s with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        ));
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolveListItem(ReflectionParameter|ReflectionProperty $field): string|null
    {
        return self::resolveDeclaration(
            $field,
            '(?:non-empty-)?list\\s*<\\s*(?<type>' . FieldTypeNameResolver::COLLECTION_TYPE_PATTERN . ')\\s*>',
        );
    }

    /** @return array{types: list<'bool'|'float'|'int'|'string'|class-string>, required: int}|null */
    public static function resolveTuple(ReflectionParameter|ReflectionProperty $field): array|null
    {
        $typePattern = '(?:' . FieldTypeNameResolver::COLLECTION_TYPE_PATTERN . ')';
        $itemPattern = '(?:[0-9]+\\??\\s*:\\s*)?' . $typePattern;
        $types = self::declarationType(
            $field,
            'array\\s*\\{\\s*(?<type>(?:' . $itemPattern . '\\s*(?:,\\s*' . $itemPattern . '\\s*)*,?)?)\\s*\\}',
        );

        if ($types === null) {
            return null;
        }

        return TupleTypeResolver::resolve($field, $types);
    }

    public static function isNonEmptyList(ReflectionParameter|ReflectionProperty $field): bool
    {
        return self::matchesDeclaration($field, 'non-empty-list\\s*<.+>');
    }

    public static function listDeclaration(ReflectionParameter|ReflectionProperty $field, string $itemType): string
    {
        $isNonEmpty = self::isNonEmptyList($field);
        $declaration = $isNonEmpty ? 'non-empty-list' : 'list';

        return sprintf('%s<%s>', $declaration, $itemType);
    }

    /**
     * @return 'bool'|'float'|'int'|'string'|class-string|null
     */
    private static function resolveDeclaration(
        ReflectionParameter|ReflectionProperty $field,
        string $declarationPattern,
    ): string|null {
        $type = self::declarationType($field, $declarationPattern);

        return $type === null ? null : FieldTypeNameResolver::resolvePhpDoc($field, $type);
    }

    private static function declarationType(
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

        return is_string($type) ? $type : null;
    }

    private static function matchesDeclaration(
        ReflectionParameter|ReflectionProperty $field,
        string $declarationPattern,
    ): bool {
        return preg_match(self::declarationPattern($field, $declarationPattern), self::docComment($field)) === 1;
    }

    /** @return non-empty-string */
    public static function declarationPattern(
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

    public static function docComment(ReflectionParameter|ReflectionProperty $field): string
    {
        $docComment = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();

        return $docComment === false ? '' : $docComment;
    }
}
