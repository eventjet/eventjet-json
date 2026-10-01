<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
enum ImagePullPolicy: string
{
    case Always = 'Always';
    case IfNotPresent = 'IfNotPresent';
    case Never = 'Never';
}
