<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class PriceRecurring
{
    public function __construct(
        public RecurringInterval $interval,
        public int $interval_count,
        public UsageType $usage_type,
    ) {}
}
