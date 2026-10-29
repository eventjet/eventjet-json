<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final readonly class AutomaticTax
{
    public function __construct(
        public bool $enabled,
        public TaxLiability|null $liability,
        public AutomaticTaxStatus|null $status,
    ) {}
}
