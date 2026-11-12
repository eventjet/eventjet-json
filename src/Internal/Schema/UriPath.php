<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * Merges and normalizes the paths of URI references, as RFC 3986 defines.
 *
 * @internal
 */
final class UriPath
{
    public static function merge(string|null $authority, string $basePath, string $path): string
    {
        if ($authority !== null && $basePath === '') {
            return '/' . $path;
        }
        $slash = strrpos($basePath, needle: '/');
        return $slash === false ? $path : substr($basePath, offset: 0, length: $slash + 1) . $path;
    }

    public static function removeDotSegments(string $path): string
    {
        $output = '';
        while ($path !== '') {
            if (str_starts_with($path, '/../') || $path === '/..') {
                $path = '/' . substr($path, offset: 4);
                $slash = strrpos($output, needle: '/');
                $output = $slash === false ? '' : substr($output, offset: 0, length: $slash);
                continue;
            }
            $rest = self::withoutDotSegment($path);
            if ($rest !== null) {
                $path = $rest;
                continue;
            }
            $next = strpos($path, needle: '/', offset: 1);
            $segment = $next === false ? $path : substr($path, offset: 0, length: $next);
            $output .= $segment;
            $path = substr($path, strlen($segment));
        }
        return $output;
    }

    /** Returns the path without its leading `.` or `..` segment, or null if it doesn't start with one. */
    private static function withoutDotSegment(string $path): string|null
    {
        return match (true) {
            str_starts_with($path, '../') => substr($path, offset: 3),
            str_starts_with($path, './'), str_starts_with($path, '/./') => substr($path, offset: 2),
            $path === '/.' => '/',
            $path === '.', $path === '..' => '',
            default => null,
        };
    }
}
