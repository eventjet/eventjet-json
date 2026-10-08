<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum TaxLiabilityType: string
{
    case Account = 'account';
    case Self = 'self';
}
