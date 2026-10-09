<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocFieldType
{
    /** @var array<string, array<string, list<array{PhpDocType, string}>>> */
    private static array $declarations = [];
    /** @var array<string, array<string, array<string, PhpDocType>>> */
    private static array $parameters = [];

    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        $tag = $field instanceof ReflectionParameter ? '@param' : '@var';
        $doc = $doc === false ? '' : $doc;
        self::$declarations[$tag][$doc] ??= self::parse($doc, $tag);
        $types = self::$declarations[$tag][$doc];
        if ($field instanceof ReflectionParameter) {
            self::$parameters[$tag][$doc] ??= self::parameters($types);
            $byName = self::$parameters[$tag][$doc];
            return $byName[$field->getName()] ?? null;
        }
        foreach ($types as $parsed) {
            [$type, $remainder] = $parsed;
            $suffixPattern = '/\\A(?:\\s+[^\\s|&<>\\[\\],?:{}].*)?\\z/s';
            $validSuffix = preg_match($suffixPattern, $remainder) === 1;

            if ($validSuffix) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param list<array{PhpDocType, string}> $types
     * @return array<string, PhpDocType>
     */
    private static function parameters(array $types): array
    {
        $parameters = [];
        foreach ($types as [$type, $remainder]) {
            $matches = [];
            preg_match('/\\A\\s+\\$([^\\s]+)(?:\\s|$)/', $remainder, $matches);
            $parameters[$matches[1] ?? ''] = $type;
        }
        return $parameters;
    }

    /** @return list<array{PhpDocType, string}> */
    private static function parse(string $doc, string $tag): array
    {
        $doc =
            preg_replace('/^[ \t]*\*[ \t]?/m', replacement: '', subject: str_replace('*/', replace: '', subject: $doc))
            ?? '';
        $matches = [];
        $pattern = strpbrk($doc, characters: "'\"") === false
            ? '/' . $tag . '\s+([^@]+)/'
            : '/' . $tag . '\s+(?=([\s\S]*))/';
        preg_match_all($pattern, $doc, $matches);
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
