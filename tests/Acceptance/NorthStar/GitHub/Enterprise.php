<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
final readonly class Enterprise
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $node_id,
    ) {}
}
