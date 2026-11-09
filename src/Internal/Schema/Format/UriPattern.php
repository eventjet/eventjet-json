<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

/**
 * Builds the PCRE patterns for URIs of RFC 3986 and IRIs of RFC 3987.
 *
 * @internal
 */
final readonly class UriPattern
{
    /**
     * The ucschar ranges of RFC 3987 as alternatives, each with a leading `|`. Each range has a class of its own
     * because PCRE2 10.46 fails to match some code points of a class with many ranges above U+00FF.
     */
    public const string UCSCHAR =
        '|[\x{A0}-\x{D7FF}]|[\x{F900}-\x{FDCF}]|[\x{FDF0}-\x{FFEF}]|[\x{10000}-\x{1FFFD}]|[\x{20000}-\x{2FFFD}]'
            . '|[\x{30000}-\x{3FFFD}]|[\x{40000}-\x{4FFFD}]|[\x{50000}-\x{5FFFD}]|[\x{60000}-\x{6FFFD}]'
            . '|[\x{70000}-\x{7FFFD}]|[\x{80000}-\x{8FFFD}]|[\x{90000}-\x{9FFFD}]|[\x{A0000}-\x{AFFFD}]'
            . '|[\x{B0000}-\x{BFFFD}]|[\x{C0000}-\x{CFFFD}]|[\x{D0000}-\x{DFFFD}]|[\x{E1000}-\x{EFFFD}]';
    /** The iprivate ranges of RFC 3987, in the form of {@see self::UCSCHAR}. */
    public const string IPRIVATE = '|[\x{E000}-\x{F8FF}]|[\x{F0000}-\x{FFFFD}]|[\x{100000}-\x{10FFFD}]';
    private const string UNRESERVED = 'A-Za-z0-9\-._\~';
    private const string SUB_DELIMS = '!$&\'()*+,;=';
    private const string PCHAR = self::UNRESERVED . self::SUB_DELIMS . ':@';
    private const string SEGMENT_NZ_NC = self::UNRESERVED . self::SUB_DELIMS . '@';
    private const string USERINFO = self::UNRESERVED . self::SUB_DELIMS . ':';
    private const string REG_NAME = self::UNRESERVED . self::SUB_DELIMS;
    private const string PCT = '%[0-9A-Fa-f]{2}';

    private string $pchar;

    /**
     * @param string $ucschar The unreserved characters beyond those of a URI, as {@see self::UCSCHAR} or ''.
     * @param string $private The characters that a query allows beyond those of a URI, as {@see self::IPRIVATE} or ''.
     */
    public function __construct(
        private string $ucschar,
        private string $private,
    ) {
        $this->pchar = $this->character(self::PCHAR);
    }

    /** @return non-empty-string */
    public function absolute(): string
    {
        return '~^' . $this->absolutePart() . $this->tail() . '$~uD';
    }

    /** @return non-empty-string */
    public function reference(): string
    {
        return '~^(?J)(?:' . $this->absolutePart() . '|' . $this->relativePart() . ')' . $this->tail() . '$~uD';
    }

    private function absolutePart(): string
    {
        $pathRootless = $this->pchar . '+(?:\/' . $this->pchar . '*)*';
        return '[A-Za-z][A-Za-z0-9+.\-]*:(?:' . $this->authorityAndPath() . '|' . $pathRootless . ')?';
    }

    private function relativePart(): string
    {
        $first = $this->character(self::SEGMENT_NZ_NC) . '+';
        return '(?:' . $this->authorityAndPath() . '|' . $first . '(?:\/' . $this->pchar . '*)*)?';
    }

    /** The `//` authority path-abempty and the path-absolute alternatives. */
    private function authorityAndPath(): string
    {
        $userinfo = $this->character(self::USERINFO) . '*';
        $regName = $this->character(self::REG_NAME) . '*';
        $authority = '(?:' . $userinfo . '@)?(?:\[(?<ip>[^\]]*)\]|' . $regName . ')(?::\d*)?';
        $pathAbempty = '(?:\/' . $this->pchar . '*)*';
        $pathAbsolute = '\/(?:' . $this->pchar . '+(?:\/' . $this->pchar . '*)*)?';
        return '\/\/' . $authority . $pathAbempty . '|' . $pathAbsolute;
    }

    private function tail(): string
    {
        $query = '(?:[\/?]' . $this->private . '|' . $this->pchar . ')*';
        $fragment = '(?:' . $this->pchar . '|[\/?])*';
        return '(?:\?' . $query . ')?(?:#' . $fragment . ')?';
    }

    /** One of the ASCII characters of a class body, a percent-encoded octet, or a ucschar. */
    private function character(string $ascii): string
    {
        return '(?:[' . $ascii . ']|' . self::PCT . $this->ucschar . ')';
    }
}
