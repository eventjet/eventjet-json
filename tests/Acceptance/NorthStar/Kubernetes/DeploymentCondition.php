<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final class DeploymentCondition
{
    public DeploymentConditionType|null $type = null;

    public ConditionStatus|null $status = null;

    public string $lastUpdateTime = '';

    public string $lastTransitionTime = '';

    public string $reason = '';

    public string $message = '';
}
