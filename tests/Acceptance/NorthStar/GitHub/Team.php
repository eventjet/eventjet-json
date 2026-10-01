<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class Team
{
    public function __construct(
        public int $id,
        public string $node_id,
        public string $name,
        public string $slug,
    ) {}
}
