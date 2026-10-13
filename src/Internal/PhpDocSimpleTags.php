<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function explode;
use function preg_match;
use function str_contains;
use function strlen;
use function strpbrk;
use function strpos;
use function substr;
use function trim;

/**
 * Reads doc comments that use only a small part of the PHPDoc grammar, for which phpstan/phpdoc-parser's result does
 * not depend on backtracking: ASCII lines of text, and `@param` and `@var` tags whose types fit on one line. See
 * {@see PhpDocSimpleTag} for the tags and {@see PhpDocSimpleType} for the types. Any other doc comment needs
 * {@see PhpDocTagParser}.
 *
 * @internal
 */
final class PhpDocSimpleTags
{
    /** Printable ASCII and tabs, with LF or CRLF line ends, and whitespace after the opening. */
    private const string DOC = '~\A/\*\*(?=[ \t]|\r?\n)(?:[\t\x20-\x7E]|\r?\n)*+\z~';

    /** A line end with the indentation and the `*` that may follow, which phpdoc-parser reads as one token. */
    private const string LINE_START = '~\r?\n[\t ]*+\*?+~';

    /**
     * Returns the tags like {@see PhpDocTagParser::tags()}, or null if the doc comment needs the full parser.
     *
     * @return list<array{string, string, PhpDocType}>|null
     */
    public static function tags(string $doc): array|null
    {
        $body = self::body($doc);
        if ($body === null) {
            return null;
        }
        $tags = [];
        foreach (explode("\n", (string) preg_replace(self::LINE_START, replacement: "\n", subject: $body)) as $line) {
            $content = trim($line, characters: " \t");
            $tagged = ($content[0] ?? '') === '@';
            $tag = $tagged ? PhpDocSimpleTag::parse($content) : null;
            $simple = $tagged ? $tag !== null : !self::continuesTag($content);
            if (!$simple) {
                return null;
            }
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }
        return $tags;
    }

    /** Returns the doc comment between its delimiters, or null if it needs the full parser. */
    private static function body(string $doc): string|null
    {
        $end = strlen($doc) - 2;
        $simple = preg_match(self::DOC, $doc) === 1 && strpos($doc, needle: '*/') === $end;
        $body = substr($doc, offset: 3, length: $end - 3);
        $slash = strpos($body, needle: '/');
        // phpdoc-parser reads `<b>` in `Foo<b> … </b>` as an HTML element, which needs a slash before a later `>`.
        $html = $slash !== false && strpos($body, needle: '>', offset: $slash) !== false;
        return $simple && !$html ? $body : null;
    }

    /**
     * Whether a line of text may belong to a tag above it: a type may continue on a line that starts with `|`, `&`, or
     * a comment, and a double quote may start a string that spans lines.
     */
    private static function continuesTag(string $content): bool
    {
        return strpbrk($content[0] ?? '', characters: '|&/') !== false || str_contains($content, '"');
    }
}
