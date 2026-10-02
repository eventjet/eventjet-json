<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

/** @api Consumed dynamically by NorthStarTest. */
final class Relationship
{
    /** @var array<string, string>|null */
    public array|null $links;

    /**
     * @param ResourceIdentifier|list<ResourceIdentifier>|null $data
     * @param array<string, string>|null $links
     */
    public function __construct(
        public readonly ResourceIdentifier|array|null $data,
        array|null $links = null,
    ) {
        $this->links = $links;

        // json_encode omits uninitialized properties, preserving an absent optional links member.
        if ($links === null) {
            unset($this->links);
        }
    }
}
