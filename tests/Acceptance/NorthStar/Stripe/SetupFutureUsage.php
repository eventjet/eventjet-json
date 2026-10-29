<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
enum SetupFutureUsage: string
{
    case OnSession = 'on_session';
    case OffSession = 'off_session';
}
