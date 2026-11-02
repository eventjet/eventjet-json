<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class TaxRate extends TaxRateDefinition
{
    public string|null $jurisdiction = null;
    public bool $livemode = false;
    public float $percentage = 0.0;
    public string|null $state = null;
    public string|null $tax_type = null;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
