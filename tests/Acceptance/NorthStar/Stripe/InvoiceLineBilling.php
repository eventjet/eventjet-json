<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
abstract class InvoiceLineBilling extends InvoiceLineIdentity
{
    public bool $discountable = false;
    /** @var list<Discount|string> */
    public array $discounts = [];
    public string|null $invoice_item = null;
    public bool $livemode = false;
    /** @var array<string, string> */
    public array $metadata = [];
    public Period|null $period = null;
    public Price|null $price = null;
}
