<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final class Invoice extends InvoiceDelivery
{
    public AutomaticTax|null $automatic_tax = null;
    public int $period_end = 0;
    public int $period_start = 0;
    public int $post_payment_credit_notes_amount = 0;
    public int $pre_payment_credit_notes_amount = 0;
    public int $starting_balance = 0;
    /** @var list<DiscountAmount> */
    public array $total_discount_amounts = [];
    /** @var list<TaxAmount> */
    public array $total_tax_amounts = [];
    public int|null $webhooks_delivered_at = null;
}
