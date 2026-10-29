<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
enum RestartPolicy: string
{
    case Always = 'Always';
    case OnFailure = 'OnFailure';
    case Never = 'Never';
}
