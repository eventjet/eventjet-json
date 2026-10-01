<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final class PodSpec
{
    public string $serviceAccountName = '';

    public bool|null $automountServiceAccountToken = null;

    /** @var list<Container> */
    public array $containers = [];

    /** @var list<Volume> */
    public array $volumes = [];

    public RestartPolicy|null $restartPolicy = null;

    public int $terminationGracePeriodSeconds = 0;

    public string $dnsPolicy = '';

    /** @var array<string, string> */
    public array $nodeSelector = [];

    /** @var list<Toleration> */
    public array $tolerations = [];
}
