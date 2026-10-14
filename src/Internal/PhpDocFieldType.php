<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocFieldType
{
    /** @var array<string, array<string, PhpDocType>> Keyed by tag and doc comment, then by parameter name. */
    private static array $types = [];

    /**
     * Returns the PHPDoc type of a field: its `@var` tag, or the constructor's `@param` tag for a parameter. Each doc
     * comment is parsed once, even if it declares several parameters, and only if it has the tag.
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        [$doc, $tag, $name] = $field instanceof ReflectionParameter
            ? [$field->getDeclaringFunction()->getDocComment(), '@param', $field->getName()]
            : [$field->getDocComment(), '@var', ''];
        $doc = (string) $doc;
        self::$types[$tag . $doc] ??= str_contains($doc, $tag) ? self::types($doc, $tag) : [];
        return self::$types[$tag . $doc][$name] ?? null;
    }

    /**
     * Returns the first valid type of each field that a doc comment declares with a tag: `@param` types keyed by
     * parameter name, or the `@var` type keyed by an empty string.
     *
     * @param '@param'|'@var' $tag
     * @return array<string, PhpDocType>
     */
    private static function types(string $doc, string $tag): array
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
}
