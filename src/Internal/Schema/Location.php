<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

/**
 * Where a subschema is: the base URI that its references resolve against, and its JSON Pointer in its resource.
 *
 * @internal
 */
final readonly class Location
{
    public function __construct(
        public string $base,
        public Resource $resource,
        public string $pointer,
    ) {}

    public function child(string ...$tokens): self
    {
        $pointer = $this->pointer;
        foreach ($tokens as $token) {
            $pointer .= '/' . Uri::escapePointerToken($token);
        }
        return new self($this->base, $this->resource, $pointer);
    }

    public function absolute(): string
    {
        return $this->resource->uri . '#' . $this->pointer;
    }
}
