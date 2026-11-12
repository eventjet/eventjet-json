<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function explode;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function strrpos;
use function substr;
use function substr_count;

/**
 * Checks for the formats of IP addresses.
 *
 * @internal
 */
final class IpAddresses
{
    /** @mutation-free */
    public static function ipv4(string $value): bool
    {
        return (
            preg_match('/^(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)$/D', $value)
            === 1
        );
    }

    /** Checks the text forms of RFC 4291, including `::` and a trailing IPv4 address. */
    public static function ipv6(string $value): bool
    {
        $groups = $value;
        $ipv4Groups = 0;
        if (str_contains($value, '.')) {
            $colon = strrpos($value, needle: ':');
            if ($colon === false || !self::ipv4(substr($value, $colon + 1))) {
                return false;
            }
            $groups = substr($value, offset: 0, length: $colon + 1);
            $ipv4Groups = 2;
            if (!str_ends_with($groups, '::')) {
                $groups = substr($groups, offset: 0, length: -1);
            }
        }
        $compressions = substr_count($groups, needle: '::');
        $count = $ipv4Groups;
        foreach ($compressions === 1 ? explode('::', $groups) : [$groups] as $part) {
            if ($part === '') {
                continue;
            }
            foreach (explode(':', $part) as $group) {
                $hex = preg_match('/^[0-9A-Fa-f]{1,4}$/D', $group);
                if ($hex !== 1) {
                    return false;
                }
                $count++;
            }
        }
        return $compressions === 1 ? $count <= 7 : $count === 8;
    }
}
