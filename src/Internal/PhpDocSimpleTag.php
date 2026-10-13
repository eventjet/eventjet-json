<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function preg_match;
use function str_ends_with;

/**
 * Reads a `@param` or `@var` tag line for {@see PhpDocSimpleTags}, including the prefixed variants. The variable must
 * not be `$this`, and a description after it must not contain a double quote.
 *
 * @internal
 */
final class PhpDocSimpleTag
{
    private const string TAG =
        '~\A(@(?:phpstan-|psalm-|phan-)?+(?:param|var))[ \t]++(.+?)'
            . '(?:[ \t]++(\$(?![Tt][Hh][Ii][Ss](?![0-9A-Z_a-z]))[A-Z_a-z][0-9A-Z_a-z]*+)(?:[ \t][^"]*+)?)?\z~';

    /**
     * Returns the tag name, variable name, and type, or null if the line needs the full parser.
     *
     * @return array{string, string, PhpDocType}|null
     */
    public static function parse(string $line): array|null
    {
        $matches = [];
        preg_match(self::TAG, $line, $matches);
        $tag = $matches[1] ?? '';
        $variable = $matches[3] ?? '';
        $typed = $variable !== '' || str_ends_with($tag, needle: 'var');
        $type = $typed ? PhpDocSimpleType::parse($matches[2] ?? '') : null;
        return $type === null ? null : [$tag, $variable, $type];
    }
}
