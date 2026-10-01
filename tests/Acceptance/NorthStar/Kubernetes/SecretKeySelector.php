<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class SecretKeySelector
{
    public function __construct(
        public string $name,
        public string $key,
        public bool|null $optional,
    ) {}
}
