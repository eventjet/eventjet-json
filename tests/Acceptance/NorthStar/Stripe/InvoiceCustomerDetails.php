<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
abstract class InvoiceCustomerDetails extends InvoiceCollection
{
    public Customer|string|null $customer = null;
    public string|null $customer_email = null;
    public string|null $customer_name = null;
    public string|null $customer_phone = null;
    public Shipping|null $customer_shipping = null;
    /** @var list<Discount|string> */
    public array $discounts = [];
    /** @var array<string, string> */
    public array $metadata = [];
    public string|null $footer = null;
    public string|null $receipt_number = null;
}
