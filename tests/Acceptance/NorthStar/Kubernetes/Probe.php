<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final class Probe
{
    public HttpGetAction|null $httpGet = null;

    public int $initialDelaySeconds = 0;

    public int $timeoutSeconds = 0;

    public int $periodSeconds = 0;

    public int $successThreshold = 0;

    public int $failureThreshold = 0;
}
