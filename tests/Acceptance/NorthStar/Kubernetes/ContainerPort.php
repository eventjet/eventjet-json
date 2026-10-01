<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class ContainerPort
{
    public function __construct(
        public string|null $name,
        public int $containerPort,
        public Protocol $protocol,
    ) {}
}
