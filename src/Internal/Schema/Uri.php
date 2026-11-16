<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use function explode;
use function rawurldecode;
use function str_ends_with;
use function str_replace;
use function strpos;
use function substr;

/**
 * Resolves URI references as RFC 3986 defines.
 *
 * @internal
 */
final class Uri
{
    public static function resolve(string $base, string $reference): string
    {
        $ref = UriReference::parse($reference);
        if ($ref->scheme !== null) {
            return $ref->withPath(UriPath::removeDotSegments($ref->path))->toString();
        }
        $b = UriReference::parse($base);
        if ($ref->authority !== null) {
            return new UriReference(
                $b->scheme,
                $ref->authority,
                UriPath::removeDotSegments($ref->path),
                $ref->query,
                $ref->fragment,
            )->toString();
        }
        if ($ref->path === '') {
            return new UriReference(
                $b->scheme,
                $b->authority,
                $b->path,
                $ref->query ?? $b->query,
                $ref->fragment,
            )->toString();
        }
        $path = $ref->path[0] === '/' ? $ref->path : UriPath::merge($b->authority, $b->path, $ref->path);
        return new UriReference(
            $b->scheme,
            $b->authority,
            UriPath::removeDotSegments($path),
            $ref->query,
            $ref->fragment,
        )->toString();
    }

    /**
     * Splits a URI into the part before the fragment and the fragment, which is null without a `#`.
     *
     * @return array{string, string|null}
     */
    public static function split(string $uri): array
    {
        $hash = strpos($uri, needle: '#');
        if ($hash === false) {
            return [$uri, null];
        }
        return [substr($uri, offset: 0, length: $hash), substr($uri, $hash + 1)];
    }

    public static function withoutFragment(string $uri): string
    {
        return self::split($uri)[0];
    }

    /** Drops a trailing `#`, which names the same resource as no fragment. */
    public static function withoutEmptyFragment(string $uri): string
    {
        return str_ends_with($uri, '#') ? substr($uri, offset: 0, length: -1) : $uri;
    }

    /**
     * Turns a JSON Pointer from a URI fragment into its reference tokens.
     *
     * @return list<string>|null Null if the fragment isn't a JSON Pointer.
     */
    public static function pointerTokens(string $fragment): array|null
    {
        $pointer = rawurldecode($fragment);
        if ($pointer === '') {
            return [];
        }
        if ($pointer[0] !== '/') {
            return null;
        }
        $tokens = [];
        foreach (explode(separator: '/', string: substr($pointer, offset: 1)) as $segment) {
            $tokens[] = str_replace(['~1', '~0'], ['/', '~'], $segment);
        }
        return $tokens;
    }

    public static function escapePointerToken(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
