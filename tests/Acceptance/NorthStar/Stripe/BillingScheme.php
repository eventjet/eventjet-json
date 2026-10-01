<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum BillingScheme: string
{
    case PerUnit = 'per_unit';
    case Tiered = 'tiered';
}
