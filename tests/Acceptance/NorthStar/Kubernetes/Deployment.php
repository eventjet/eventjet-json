<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final class Deployment
{
    public DeploymentStatus $status;

    public function __construct(
        public readonly string $apiVersion,
        public readonly string $kind,
        public readonly ObjectMeta $metadata,
        public readonly DeploymentSpec $spec,
    ) {
        $this->status = new DeploymentStatus();
    }
}
