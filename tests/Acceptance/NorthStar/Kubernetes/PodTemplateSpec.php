<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final readonly class PodTemplateSpec
{
    public function __construct(
        public PodTemplateMetadata $metadata,
        public PodSpec $spec,
    ) {}
}
