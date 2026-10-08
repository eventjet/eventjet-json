<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum PriceType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
