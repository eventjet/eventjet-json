<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function trim;

/** @internal */
final class PhpDocFieldType
{
    /** @var array<string, MetadataCache<list<array{PhpDocType, string}>>> */
    private static array $declarations = [];

    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        $tag = $field instanceof ReflectionParameter ? '@param' : '@var';
        /** @var MetadataCache<list<array{PhpDocType, string}>> $cache */
        $cache = self::$declarations[$tag] ?? new MetadataCache();
        self::$declarations[$tag] = $cache;
        $doc = $doc === false ? '' : $doc;
        $types = $cache->resolve(
            $doc,
            /** @return list<array{PhpDocType, string}> */ static fn(): array => self::parse($doc, $tag),
        );
        foreach ($types as $parsed) {
            [$type, $remainder] = $parsed;
            $suffixPattern = $field instanceof ReflectionParameter
                ? '/\\A\\s+\\$' . $field->getName() . '(?:\\s|$)/'
                : '/\\A(?:\\s+[^\\s|&<>\\[\\],?:{}].*)?\\z/s';
            $validSuffix = preg_match($suffixPattern, $remainder) === 1;

            if ($validSuffix) {
                return $type;
            }
        }

        return null;
    }

    /** @return list<array{PhpDocType, string}> */
    private static function parse(string $doc, string $tag): array
    {
        $doc =
            preg_replace('/^[ \t]*\*[ \t]?/m', replacement: '', subject: str_replace('*/', replace: '', subject: $doc))
            ?? '';
        $matches = [];
        // Keep the remaining comment intact: quoted literals can contain @ characters.
        preg_match_all('/' . $tag . '\s+(?=([\s\S]*))/', $doc, $matches);
        /** @var array{list<string>, list<string>} $matches */
        $types = [];
        foreach ($matches[1] as $source) {
            $parsed = PhpDocTypeParser::prefix(trim($source, characters: " \t\r\n\v\f"));
            if ($parsed !== null) {
                $types[] = $parsed;
            }
        }
        return $types;
    }
}
