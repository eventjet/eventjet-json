<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final class Discount extends DiscountDefinition
{
    public string|null $invoice = null;
    public string|null $invoice_item = null;
    public string|null $promotion_code = null;
    public int $start = 0;
    public string|null $subscription = null;
}
