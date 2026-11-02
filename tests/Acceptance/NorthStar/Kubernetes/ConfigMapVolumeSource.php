<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final readonly class ConfigMapVolumeSource
{
    public function __construct(
        public string $name,
        public int|null $defaultMode,
        public bool|null $optional,
    ) {}
}
