<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class RollingUpdate
{
    public function __construct(
        public int|string $maxUnavailable,
        public int|string $maxSurge,
    ) {}
}
