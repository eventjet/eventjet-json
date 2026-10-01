<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
enum SelectorOperator: string
{
    case In = 'In';
    case NotIn = 'NotIn';
    case Exists = 'Exists';
    case DoesNotExist = 'DoesNotExist';
}
