<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function preg_quote;

/**
 * Builds the PCRE patterns for URIs of RFC 3986 and IRIs of RFC 3987.
 *
 * @internal
 */
final readonly class UriPattern
{
    public const string UNRESERVED = 'A-Za-z0-9\-._\~';
    public const string UCSCHAR =
        '\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}'
            . '\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}'
            . '\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}'
            . '\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}';
    public const string IPRIVATE = '\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}';
    private const string SUB_DELIMS = '!$&\'()*+,;=';
    private const string PCT = '%[0-9A-Fa-f]{2}';

    private string $pchar;

    /**
     * @param string $unreserved The unreserved characters, as a character class body.
     * @param string $private The characters that a query allows beyond those of a URI.
     */
    public function __construct(
        private string $unreserved,
        private string $private,
    ) {
        $this->pchar = '(?:[' . $unreserved . preg_quote(self::SUB_DELIMS, delimiter: '~') . ':@]|' . self::PCT . ')';
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
        $sub = preg_quote(self::SUB_DELIMS, delimiter: '~');
        $first = '(?:[' . $this->unreserved . $sub . '@]|' . self::PCT . ')+';
        return '(?:' . $this->authorityAndPath() . '|' . $first . '(?:\/' . $this->pchar . '*)*)?';
    }

    /** The `//` authority path-abempty and the path-absolute alternatives. */
    private function authorityAndPath(): string
    {
        $sub = preg_quote(self::SUB_DELIMS, delimiter: '~');
        $userinfo = '(?:[' . $this->unreserved . $sub . ':]|' . self::PCT . ')*';
        $regName = '(?:[' . $this->unreserved . $sub . ']|' . self::PCT . ')*';
        $authority = '(?:' . $userinfo . '@)?(?:\[(?<ip>[^\]]*)\]|' . $regName . ')(?::\d*)?';
        $pathAbempty = '(?:\/' . $this->pchar . '*)*';
        $pathAbsolute = '\/(?:' . $this->pchar . '+(?:\/' . $this->pchar . '*)*)?';
        return '\/\/' . $authority . $pathAbempty . '|' . $pathAbsolute;
    }

    private function tail(): string
    {
        $query = '(?:' . $this->pchar . '|[\/?' . $this->private . '])*';
        $fragment = '(?:' . $this->pchar . '|[\/?])*';
        return '(?:\?' . $query . ')?(?:#' . $fragment . ')?';
    }
}
