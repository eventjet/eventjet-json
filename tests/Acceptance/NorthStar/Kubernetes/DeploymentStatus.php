<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final class DeploymentStatus
{
    public int $observedGeneration = 0;

    public int $replicas = 0;

    public int $updatedReplicas = 0;

    public int $readyReplicas = 0;

    public int $availableReplicas = 0;

    public int $unavailableReplicas = 0;

    public int|null $collisionCount = null;

    /** @var list<DeploymentCondition> */
    public array $conditions = [];
}
