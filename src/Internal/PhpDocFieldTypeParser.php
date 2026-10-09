<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_replace;
use function trim;

/** @internal */
final class PhpDocFieldTypeParser
{
    /** @return list<array{PhpDocType, string}> */
    public static function parse(string $doc, string $tag): array
    {
        $doc =
            preg_replace('/^[ \t]*\*[ \t]?/m', replacement: '', subject: str_replace('*/', replace: '', subject: $doc))
            ?? '';
        $matches = [];
        $pattern = str_contains($doc, "'") || str_contains($doc, '"')
            // Keep the remaining comment intact: quoted literals can contain @ characters.
            ? '/' . $tag . '\s+(?=([\s\S]*))/'
            : '/' . $tag . '\s+([^@]+)/';
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
