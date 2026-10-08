<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum UsageType: string
{
    case Licensed = 'licensed';
    case Metered = 'metered';
}
