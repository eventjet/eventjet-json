<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final class InvoiceLine extends InvoiceLineBilling
{
    public bool $proration = false;
    public int|null $quantity = null;
    public string|null $subscription = null;
    public string|null $subscription_item = null;
    /** @var list<TaxAmount> */
    public array $tax_amounts = [];
    public LineType|null $type = null;
    public string|null $unit_amount_excluding_tax = null;
}
