<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum CouponDuration: string
{
    case Forever = 'forever';
    case Once = 'once';
    case Repeating = 'repeating';
}
