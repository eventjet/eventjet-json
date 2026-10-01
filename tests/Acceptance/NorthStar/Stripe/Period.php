<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class Period
{
    public function __construct(
        public int $start,
        public int $end,
    ) {}
}
