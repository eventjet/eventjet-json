<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum AutomaticTaxStatus: string
{
    case Complete = 'complete';
    case Failed = 'failed';
    case RequiresLocationInputs = 'requires_location_inputs';
}
