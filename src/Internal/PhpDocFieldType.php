<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function enum_exists;
use function interface_exists;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocFieldType
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

    /** Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration. */
    public static function unrecognized(ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return UnsupportedCollection::unrecognized(
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        );
    }

    /**
     * Returns the first valid type of each field that a doc comment declares with a tag: `@param` types keyed by
     * parameter name, or the `@var` type keyed by an empty string.
     *
     * @param '@param'|'@var' $tag
     * @return array<string, PhpDocType>
     */
    public static function types(string $doc, string $tag): array
    {
        $doc =
            preg_replace('/^[ \t]*\*[ \t]?/m', replacement: '', subject: str_replace('*/', replace: '', subject: $doc))
            ?? '';
        // Quoted literals may contain "@", so each source then extends to the end and the parser finds where the type ends.
        $source = strpbrk($doc, characters: "'\"") === false ? '([^@]+)' : '(?=([\s\S]*))';
        $matches = [];
        preg_match_all('/' . $tag . '\s+' . $source . '/', $doc, $matches);
        $suffix = $tag === '@param'
            ? '/\A\s+\$([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)(?:\s|$)/'
            : '/\A()(?:\s+[^\s|&<>\[\],?:{}].*)?\z/s';
        /** @var array{list<string>, list<string>} $matches */
        $sources = $matches[1];
        $types = [];
        foreach ($sources as $source) {
            $parsed = PhpDocTypeParser::prefix(trim($source, characters: " \t\r\n\v\f"));
            $name = [];
            $valid = $parsed !== null && preg_match($suffix, $parsed[1], $name) === 1;
            if ($valid) {
                /** @var array{string, string} $name */
                $types[$name[1]] ??= $parsed[0];
            }
        }
        return $types;
    }

    /**
     * Resolves a PHPDoc scalar, enum, or class name, or returns null if it names none of them.
     *
     * @return 'bool'|'float'|'int'|'string'|class-string|null
     */
    public static function resolveName(ReflectionParameter|ReflectionProperty $field, string $type): string|null
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
