<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
abstract class InvoiceCustomerDetails extends InvoiceCollection
{
    public Customer|string|null $customer = null;
    public string|null $customer_email = null;
    public string|null $customer_name = null;
    public string|null $customer_phone = null;
    public Shipping|null $customer_shipping = null;
    /** @var list<Discount|string> */
    public array $discounts = [];
    public string|null $footer = null;
    public string|null $receipt_number = null;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
