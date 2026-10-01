<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class DiscountAmount
{
    public function __construct(
        public int $amount,
        public string $discount,
    ) {}
}
