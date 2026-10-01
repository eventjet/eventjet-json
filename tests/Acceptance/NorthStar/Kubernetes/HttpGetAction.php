<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class HttpGetAction
{
    public function __construct(
        public string $path,
        public int|string $port,
        public HttpScheme $scheme,
    ) {}
}
