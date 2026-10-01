<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class Volume
{
    public function __construct(
        public string $name,
        public ConfigMapVolumeSource|null $configMap,
        public EmptyDirVolumeSource|null $emptyDir,
    ) {}
}
