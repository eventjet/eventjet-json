<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final readonly class ObjectFieldSelector
{
    public function __construct(
        public string $apiVersion,
        public string $fieldPath,
    ) {}
}
