<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum RecurringInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
