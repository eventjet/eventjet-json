<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
enum Protocol: string
{
    case Tcp = 'TCP';
    case Udp = 'UDP';
    case Sctp = 'SCTP';
}
