<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final readonly class TaxLiability
{
    public function __construct(
        public TaxLiabilityType $type,
        public string|null $account,
    ) {}
}
