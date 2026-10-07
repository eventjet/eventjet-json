<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class Relationship
{
    /** @var ArrayObject<string, string>|null */
    public ArrayObject|null $links;

    /**
     * @param ResourceIdentifier|list<ResourceIdentifier>|null $data
     * @param ArrayObject<string, string>|null $links
     */
    public function __construct(
        public readonly ResourceIdentifier|array|null $data,
        ArrayObject|null $links = null,
    ) {
        $this->links = $links;

        // json_encode omits uninitialized properties, preserving an absent optional links member.
        if ($links === null) {
            unset($this->links);
        }
    }
}
