<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function preg_match;
use function str_starts_with;
use function strcspn;
use function strpos;
use function substr;

/**
 * Checks URIs of RFC 3986 and IRIs of RFC 3987. It splits a value into its parts with string functions, the way
 * appendix B of RFC 3986 does, and checks the characters of each part with {@see Units}.
 *
 * @internal
 */
final readonly class UriSyntax
{
    /**
     * The ucschar ranges of RFC 3987 as alternatives, each with a leading `|`. Each range has a class of its own
     * because PCRE2 10.46 fails to match some code points of a class with many ranges above U+00FF.
     */
    private const string UCSCHAR =
        '|[\x{A0}-\x{D7FF}]++|[\x{F900}-\x{FDCF}]++|[\x{FDF0}-\x{FFEF}]++|[\x{10000}-\x{1FFFD}]++'
            . '|[\x{20000}-\x{2FFFD}]++|[\x{30000}-\x{3FFFD}]++|[\x{40000}-\x{4FFFD}]++|[\x{50000}-\x{5FFFD}]++'
            . '|[\x{60000}-\x{6FFFD}]++|[\x{70000}-\x{7FFFD}]++|[\x{80000}-\x{8FFFD}]++|[\x{90000}-\x{9FFFD}]++'
            . '|[\x{A0000}-\x{AFFFD}]++|[\x{B0000}-\x{BFFFD}]++|[\x{C0000}-\x{CFFFD}]++|[\x{D0000}-\x{DFFFD}]++'
            . '|[\x{E1000}-\x{EFFFD}]++';
    /** The iprivate ranges of RFC 3987, in the form of {@see self::UCSCHAR}. */
    private const string IPRIVATE = '|[\x{E000}-\x{F8FF}]++|[\x{F0000}-\x{FFFFD}]++|[\x{100000}-\x{10FFFD}]++';
    /** The alternatives that every part of a URI allows beyond its ASCII characters, each with a leading `|`. */
    public const string URI_CHARACTERS = '|%[0-9A-Fa-f]{2}';
    /** The alternatives that every part of an IRI allows beyond its ASCII characters. */
    public const string IRI_CHARACTERS = self::URI_CHARACTERS . self::UCSCHAR;
    /** The alternatives that the query of an IRI allows beyond its ASCII characters. */
    public const string IRI_QUERY_CHARACTERS = self::IRI_CHARACTERS . self::IPRIVATE;
    private const string PCHAR = UriAuthority::REG_NAME . ':@';
    private const string PATH = self::PCHAR . '\/';
    private const string QUERY = self::PCHAR . '\/?';

    /**
     * @param string $characters The alternatives that every part allows beyond its ASCII characters, as
     *     {@see self::URI_CHARACTERS} or {@see self::IRI_CHARACTERS}.
     * @param string $queryCharacters The alternatives that the query allows beyond its ASCII characters.
     */
    public function __construct(
        private string $characters,
        private string $queryCharacters,
    ) {}

    public function absolute(string $value): bool
    {
        $schemeEnd = strcspn($value, characters: ':/?#');
        $scheme = preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*+$/D', substr($value, offset: 0, length: $schemeEnd)) === 1;
        return (
            $scheme
            && substr($value, $schemeEnd, length: 1) === ':'
            && $this->hierPart(substr($value, $schemeEnd + 1))
        );
    }

    /**
     * A colon before the first `/`, `?` or `#` always ends a scheme: a relative reference can't have one in its first
     * segment.
     */
    public function reference(string $value): bool
    {
        $schemeEnd = strcspn($value, characters: ':/?#');
        return substr($value, $schemeEnd, length: 1) === ':' ? $this->absolute($value) : $this->hierPart($value);
    }

    /** Checks the hierarchical part with its query and fragment. A missing part counts as an empty one. */
    private function hierPart(string $value): bool
    {
        [$value, $fragment] = self::cut($value, '#');
        [$value, $query] = self::cut($value, '?');
        $authority = '';
        if (str_starts_with($value, '//')) {
            $authorityEnd = 2 + strcspn($value, characters: '/', offset: 2);
            $authority = substr($value, offset: 2, length: $authorityEnd - 2);
            $value = substr($value, $authorityEnd);
        }
        return (
            UriAuthority::valid($authority, $this->characters)
            && Units::cover($value, '[' . self::PATH . ']++' . $this->characters)
            && Units::cover($query, '[' . self::QUERY . ']++' . $this->queryCharacters)
            && Units::cover($fragment, '[' . self::QUERY . ']++' . $this->characters)
        );
    }

    /** @return array{string, string} The text before the first separator and the text after it, or the text and ''. */
    private static function cut(string $text, string $separator): array
    {
        $position = strpos($text, $separator);
        if ($position === false) {
            return [$text, ''];
        }
        return [substr($text, offset: 0, length: $position), substr($text, $position + 1)];
    }
}
