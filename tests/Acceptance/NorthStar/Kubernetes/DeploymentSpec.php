<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final class DeploymentSpec
{
    public int $replicas = 0;

    public LabelSelector|null $selector = null;

    public DeploymentStrategy|null $strategy = null;

    public int $minReadySeconds = 0;

    public int $revisionHistoryLimit = 0;

    public int $progressDeadlineSeconds = 0;

    public bool $paused = false;

    public PodTemplateSpec|null $template = null;
}
