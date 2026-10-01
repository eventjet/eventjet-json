<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class ResourceRequirements
{
    /**
     * @param array<string, string> $limits
     * @param array<string, string> $requests
     */
    public function __construct(
        public array $limits,
        public array $requests,
    ) {}
}
