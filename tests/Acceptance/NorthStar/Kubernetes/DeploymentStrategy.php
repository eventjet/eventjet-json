<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final readonly class DeploymentStrategy
{
    public function __construct(
        public StrategyType $type,
        public RollingUpdate|null $rollingUpdate,
    ) {}
}
