<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
abstract class InvoiceLineBilling extends InvoiceLineIdentity
{
    public bool $discountable = false;
    /** @var list<Discount|string> */
    public array $discounts = [];
    public string|null $invoice_item = null;
    public bool $livemode = false;
    public Period|null $period = null;
    public Price|null $price = null;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
