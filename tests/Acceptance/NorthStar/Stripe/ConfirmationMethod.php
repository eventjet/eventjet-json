<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum ConfirmationMethod: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
