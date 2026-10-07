<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class InvoiceAmounts extends InvoiceIdentity
{
    public int $amount_due = 0;
    public int $amount_paid = 0;
    public int $amount_remaining = 0;
    public int $amount_shipping = 0;
    public int $subtotal = 0;
    public int|null $subtotal_excluding_tax = null;
    public int|null $tax = null;
    public int $total = 0;
    public int|null $total_excluding_tax = null;
}
