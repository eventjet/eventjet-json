<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class Capabilities
{
    /**
     * @param list<string> $drop
     * @param list<string> $add
     */
    public function __construct(
        public array $drop,
        public array $add,
    ) {}
}
