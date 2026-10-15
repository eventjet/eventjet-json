<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class Customer extends CustomerDefinition
{
    public string $invoice_prefix = '';
    public InvoiceSettings|null $invoice_settings = null;
    public bool $livemode = false;
    public string|null $name = null;
    public int $next_invoice_sequence = 0;
    public string|null $phone = null;
    /** @var list<string> */
    public array $preferred_locales = [];
    public Shipping|null $shipping = null;
    public TaxExempt|null $tax_exempt = null;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
