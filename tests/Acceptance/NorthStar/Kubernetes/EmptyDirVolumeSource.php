<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class EmptyDirVolumeSource
{
    public function __construct(
        public string $medium,
        public string|null $sizeLimit,
    ) {}
}
