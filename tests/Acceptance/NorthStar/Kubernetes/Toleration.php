<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class Toleration
{
    public function __construct(
        public string $key,
        public string $operator,
        public string|null $value,
        public string|null $effect,
        public int|null $tolerationSeconds,
    ) {}
}
