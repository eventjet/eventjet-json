<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
final readonly class License
{
    public function __construct(
        public string $key,
        public string $name,
        public string $spdx_id,
        public string|null $url,
        public string $node_id,
    ) {}
}
