<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
final readonly class Organization
{
    public function __construct(
        public string $login,
        public int $id,
        public string $node_id,
        public string $url,
        public string $repos_url,
    ) {}
}
