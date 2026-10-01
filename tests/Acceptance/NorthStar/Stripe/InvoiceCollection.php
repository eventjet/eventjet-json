<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
abstract class InvoiceCollection extends InvoiceAmounts
{
    public int $attempt_count = 0;
    public bool $attempted = false;
    public bool $auto_advance = false;
    public BillingReason|null $billing_reason = null;
    public string|null $charge = null;
    public CollectionMethod|null $collection_method = null;
    public int|null $due_date = null;
    public int|null $ending_balance = null;
    public bool $paid = false;
}
