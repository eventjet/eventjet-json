<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
enum ConditionStatus: string
{
    case ConditionTrue = 'True';
    case ConditionFalse = 'False';
    case Unknown = 'Unknown';
}
