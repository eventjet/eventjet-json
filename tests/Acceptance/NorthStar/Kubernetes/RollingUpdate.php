<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final readonly class RollingUpdate
{
    public function __construct(
        public int|string $maxUnavailable,
        public int|string $maxSurge,
    ) {}
}
