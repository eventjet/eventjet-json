<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
enum DeploymentConditionType: string
{
    case Available = 'Available';
    case Progressing = 'Progressing';
    case ReplicaFailure = 'ReplicaFailure';
}
