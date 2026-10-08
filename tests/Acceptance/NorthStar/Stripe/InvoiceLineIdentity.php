<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class InvoiceLineIdentity
{
    public string $id = '';
    public string $object = '';
    public int $amount = 0;
    public int|null $amount_excluding_tax = null;
    public Currency|null $currency = null;
    public string|null $description = null;
    /** @var list<DiscountAmount> */
    public array $discount_amounts = [];
}
