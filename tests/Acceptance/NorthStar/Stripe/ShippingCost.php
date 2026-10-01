<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class ShippingCost
{
    /** @param list<TaxAmount> $taxes */
    public function __construct(
        public int $amount_subtotal,
        public int $amount_tax,
        public int $amount_total,
        public string $shipping_rate,
        public array $taxes,
    ) {}
}
