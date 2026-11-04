<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use function preg_match;
use function strtolower;

use const PREG_UNMATCHED_AS_NULL;

/**
 * The components of a URI reference, as RFC 3986 splits it.
 *
 * @internal
 */
final readonly class UriReference
{
    public function __construct(
        public string|null $scheme,
        public string|null $authority,
        public string $path,
        public string|null $query,
        public string|null $fragment,
    ) {}

    public static function parse(string $uri): self
    {
        $m = [];
        preg_match(
            '~^(?:([a-zA-Z][a-zA-Z0-9+.-]*):)?(?://([^/?#]*))?([^?#]*)(?:\\?([^#]*))?(?:#(.*))?$~s',
            $uri,
            $m,
            PREG_UNMATCHED_AS_NULL,
        );
        $scheme = $m[1] ?? null;
        return new self(
            $scheme === null ? null : strtolower($scheme),
            $m[2] ?? null,
            $m[3] ?? '',
            $m[4] ?? null,
            $m[5] ?? null,
        );
    }

    public function withPath(string $path): self
    {
        return new self($this->scheme, $this->authority, $path, $this->query, $this->fragment);
    }

    public function toString(): string
    {
        $uri = '';
        if ($this->scheme !== null) {
            $uri .= $this->scheme . ':';
        }
        if ($this->authority !== null) {
            $uri .= '//' . $this->authority;
        }
        $uri .= $this->path;
        if ($this->query !== null) {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== null) {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }
}
