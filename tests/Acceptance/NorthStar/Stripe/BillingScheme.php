<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum BillingScheme: string
{
    case PerUnit = 'per_unit';
    case Tiered = 'tiered';
}
