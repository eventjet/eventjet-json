<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
abstract class PriceDefinition
{
    public string $id = '';
    public string $object = '';
    public bool $active = false;
    public BillingScheme|null $billing_scheme = null;
    public Currency|null $currency = null;
    public bool $livemode = false;
    public string|null $lookup_key = null;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
