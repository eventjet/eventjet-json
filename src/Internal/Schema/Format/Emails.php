<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use Eventjet\Json\Internal\Schema\Draft;

use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Checks for the formats of email addresses.
 *
 * @internal
 */
final class Emails
{
    private const string ATEXT = 'A-Za-z0-9!#$%&\'*+\/=?^_`{|}~\-';
    private const string NON_ASCII = '\x{80}-\x{10FFFF}';
    private const string IDN_ATEXT = self::ATEXT . self::NON_ASCII;

    public static function email(string $value, Draft $draft): bool
    {
        $domain = self::mailboxDomain($value, self::ATEXT, '');
        if ($domain === null) {
            return false;
        }
        return self::addressLiteral($domain) ?? Hosts::hostname($domain, $draft);
    }

    public static function idnEmail(string $value): bool
    {
        $domain = self::mailboxDomain($value, self::IDN_ATEXT, self::NON_ASCII);
        if ($domain === null) {
            return false;
        }
        return self::addressLiteral($domain) ?? IdnHosts::hostname($domain);
    }

    /**
     * Returns the domain of an email address whose local part is valid, or null if it has none.
     *
     * @param string $atext The characters of an atom.
     * @param string $qtext The characters that a quoted string allows beyond ASCII.
     */
    private static function mailboxDomain(string $value, string $atext, string $qtext): string|null
    {
        $at = strrpos($value, needle: '@');
        if ($at === false) {
            return null;
        }
        $local = substr($value, offset: 0, length: $at);
        $dotAtom = self::dotAtom($local, $atext);
        $quotedString = self::quotedString($local, $qtext);
        return $dotAtom || $quotedString ? substr($value, offset: $at + 1) : null;
    }

    /** A dot-atom is atoms with single dots between them. */
    private static function dotAtom(string $local, string $atext): bool
    {
        return $local !== '' && Units::cover($local, '[' . $atext . ']++(?:\.(?=[' . $atext . ']))?');
    }

    private static function quotedString(string $local, string $qtext): bool
    {
        $quoted = strlen($local) >= 2 && str_starts_with($local, '"') && str_ends_with($local, '"');
        $units = '[\x20\x21\x23-\x5B\x5D-\x7E' . $qtext . ']++|\\\\[\x20-\x7E]';
        return $quoted && Units::cover(substr($local, offset: 1, length: -1), $units);
    }

    /** Returns whether an address literal such as `[127.0.0.1]` is valid, or null if the domain isn't one. */
    private static function addressLiteral(string $domain): bool|null
    {
        $m = [];
        $literal = preg_match('/^\[(?:IPv6:(.+)|(\d+\.\d+\.\d+\.\d+))\]$/iD', $domain, $m) === 1;
        if (!$literal) {
            return null;
        }
        $ipv4 = $m[2] ?? '';
        return $ipv4 !== '' ? IpAddresses::ipv4($ipv4) : IpAddresses::ipv6(substr($domain, offset: 6, length: -1));
    }
}
