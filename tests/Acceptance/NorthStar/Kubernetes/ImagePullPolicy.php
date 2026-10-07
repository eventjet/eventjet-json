<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
enum ImagePullPolicy: string
{
    case Always = 'Always';
    case IfNotPresent = 'IfNotPresent';
    case Never = 'Never';
}
