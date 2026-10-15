<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
enum HttpScheme: string
{
    case Http = 'HTTP';
    case Https = 'HTTPS';
}
