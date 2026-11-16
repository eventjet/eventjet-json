<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function preg_match;
use function str_contains;
use function str_starts_with;
use function strcspn;
use function strlen;
use function strpos;
use function strspn;
use function substr;

/**
 * Checks the authority of a URI or an IRI: the user information, the host and the port.
 *
 * @internal
 */
final class UriAuthority
{
    /** The unreserved characters and sub-delims of RFC 3986, as a class body. */
    public const string REG_NAME = 'A-Za-z0-9\-._\~!$&\'()*+,;=';
    private const string USERINFO = self::REG_NAME . ':';

    /** @param string $characters The alternatives that each part allows beyond its ASCII characters. */
    public static function valid(string $authority, string $characters): bool
    {
        $at = strpos($authority, needle: '@');
        $userinfo = Units::cover(
            $at === false ? '' : substr($authority, offset: 0, length: $at),
            '[' . self::USERINFO . ']++' . $characters,
        );
        $host = substr($authority, $at === false ? 0 : $at + 1);
        if (!str_starts_with($host, '[')) {
            $hostEnd = strcspn($host, characters: ':');
            $regName = Units::cover(
                substr($host, offset: 0, length: $hostEnd),
                '[' . self::REG_NAME . ']++' . $characters,
            );
            return $userinfo && $regName && self::port(substr($host, $hostEnd));
        }
        $close = strcspn($host, characters: ']');
        $literal = str_contains($host, ']') && self::ipLiteral(substr($host, offset: 1, length: $close - 1));
        return $userinfo && $literal && self::port(substr($host, $close + 1));
    }

    private static function ipLiteral(string $ip): bool
    {
        $future = preg_match('/^v[0-9A-Fa-f]++\.[A-Za-z0-9\-._~!$&\'()*+,;=:]++$/D', $ip) === 1;
        return $future || IpAddresses::ipv6($ip);
    }

    /** Returns whether the text after the host is empty or a colon and digits. */
    private static function port(string $text): bool
    {
        return (
            $text === ''
            || str_starts_with($text, ':')
            && strspn($text, characters: '0123456789', offset: 1) === (strlen($text) - 1)
        );
    }
}
