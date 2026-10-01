<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
enum PaymentIntentStatus: string
{
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
}
