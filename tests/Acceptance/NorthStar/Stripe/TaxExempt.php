<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum TaxExempt: string
{
    case None = 'none';
    case Exempt = 'exempt';
    case Reverse = 'reverse';
}
