<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class PodTemplateMetadata
{
    /**
     * @param array<string, string> $labels
     * @param array<string, string> $annotations
     */
    public function __construct(
        public array $labels,
        public array $annotations,
    ) {}
}
