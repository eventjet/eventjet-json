<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum TaxabilityReason: string
{
    case StandardRated = 'standard_rated';
    case NotCollecting = 'not_collecting';
}
