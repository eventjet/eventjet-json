<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum BillingReason: string
{
    case SubscriptionCycle = 'subscription_cycle';
    case Manual = 'manual';
}
