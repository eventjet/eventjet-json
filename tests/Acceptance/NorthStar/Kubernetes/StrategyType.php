<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
enum StrategyType: string
{
    case Recreate = 'Recreate';
    case RollingUpdate = 'RollingUpdate';
}
