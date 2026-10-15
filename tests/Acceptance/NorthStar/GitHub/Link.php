<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
final readonly class Link
{
    public function __construct(
        public string $href,
    ) {}
}
