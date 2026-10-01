<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final class Container extends ContainerRuntime
{
    /** @var list<ContainerPort> */
    public array $ports = [];

    /** @var list<EnvVar> */
    public array $env = [];

    public ResourceRequirements|null $resources = null;

    /** @var list<VolumeMount> */
    public array $volumeMounts = [];

    public Probe|null $readinessProbe = null;

    public SecurityContext|null $securityContext = null;
}
