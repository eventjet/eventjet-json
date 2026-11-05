<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function preg_match;
use function str_contains;

/**
 * Checks for the formats of URIs, IRIs, URI templates, and JSON Pointers.
 *
 * @internal
 */
final class Uris
{
    /** @param non-empty-string $pattern A pattern of UriPattern. */
    public static function matches(string $value, string $pattern): bool
    {
        $m = [];
        $matches = preg_match($pattern, $value, $m) === 1;
        if (!$matches) {
            return false;
        }
        $ip = $m['ip'] ?? '';
        if ($ip === '' && !str_contains($value, '[]')) {
            return true;
        }
        $future = preg_match('/^v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+$/D', $ip) === 1;
        return $future || IpAddresses::ipv6($ip);
    }

    public static function uriTemplate(string $value): bool
    {
        $literal = '(?:[^\x00-\x20"%<>\\\\^`{|}\x7F]|%[0-9A-Fa-f]{2})';
        $varchar = '(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})';
        $varname = $varchar . '(?:\.?' . $varchar . ')*';
        $varspec = $varname . '(?::[1-9]\d{0,3}|\*)?';
        $expression = '\{[+#.\/;?&=,!@|]?' . $varspec . '(?:,' . $varspec . ')*\}';
        return preg_match('~^(?:' . $literal . '|' . $expression . ')*$~uD', $value) === 1;
    }

    public static function jsonPointer(string $value): bool
    {
        return preg_match('@^(?:/(?:[^/~]|~[01])*)*$@D', $value) === 1;
    }

    public static function relativeJsonPointer(string $value): bool
    {
        return preg_match('@^(?:0|[1-9]\d*)(?:#|(?:/(?:[^/~]|~[01])*)*)$@D', $value) === 1;
    }
}
