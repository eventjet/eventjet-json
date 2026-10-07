<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final readonly class ResourceRequirements
{
    /**
     * @param ArrayObject<string, string> $limits
     * @param ArrayObject<string, string> $requests
     */
    public function __construct(
        public ArrayObject $limits,
        public ArrayObject $requests,
    ) {}
}
