<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class Shipping
{
    public function __construct(
        public Address $address,
        public string $name,
        public string|null $phone,
    ) {}
}
