<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
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

    /** @var ArrayObject<string, string> */
    public ArrayObject $nodeSelector;

    /** @var list<Toleration> */
    public array $tolerations = [];

    public function __construct()
    {
        /** @var ArrayObject<string, string> $nodeSelector */
        $nodeSelector = new ArrayObject();
        $this->nodeSelector = $nodeSelector;
    }
}
