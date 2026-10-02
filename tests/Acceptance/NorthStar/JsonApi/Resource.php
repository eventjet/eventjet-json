<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

/** @api Consumed dynamically by NorthStarTest. */
final class Resource
{
    /** @var array<string, string>|null */
    public array|null $attributes;

    /** @var array<string, string>|null */
    public array|null $links;

    /** @var array<string, Relationship>|null */
    public array|null $relationships;

    /**
     * @param array<string, string>|null $attributes
     * @param array<string, string>|null $links
     * @param array<string, Relationship>|null $relationships
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        array|null $attributes = null,
        array|null $links = null,
        array|null $relationships = null,
    ) {
        $this->attributes = $attributes;
        $this->links = $links;
        $this->relationships = $relationships;

        // json_encode omits uninitialized properties, preserving absent optional members.
        if ($attributes === null) {
            unset($this->attributes);
        }

        if ($links === null) {
            unset($this->links);
        }

        if ($relationships === null) {
            unset($this->relationships);
        }
    }
}
