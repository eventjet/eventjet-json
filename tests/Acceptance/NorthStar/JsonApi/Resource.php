<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class Resource
{
    /** @var ArrayObject<string, string>|null */
    public ArrayObject|null $attributes;

    /** @var ArrayObject<string, string>|null */
    public ArrayObject|null $links;

    /** @var ArrayObject<string, Relationship>|null */
    public ArrayObject|null $relationships;

    /**
     * @param ArrayObject<string, string>|null $attributes
     * @param ArrayObject<string, string>|null $links
     * @param ArrayObject<string, Relationship>|null $relationships
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        ArrayObject|null $attributes = null,
        ArrayObject|null $links = null,
        ArrayObject|null $relationships = null,
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
