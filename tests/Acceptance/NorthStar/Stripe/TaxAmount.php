<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final readonly class TaxAmount
{
    public function __construct(
        public int $amount,
        public bool $inclusive,
        public TaxRate|string $tax_rate,
        public TaxabilityReason $taxability_reason,
        public int $taxable_amount,
    ) {}
}
