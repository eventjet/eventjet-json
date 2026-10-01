<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final class Customer extends CustomerDefinition
{
    public string $invoice_prefix = '';
    public InvoiceSettings|null $invoice_settings = null;
    public bool $livemode = false;
    /** @var array<string, string> */
    public array $metadata = [];
    public string|null $name = null;
    public int $next_invoice_sequence = 0;
    public string|null $phone = null;
    /** @var list<string> */
    public array $preferred_locales = [];
    public Shipping|null $shipping = null;
    public TaxExempt|null $tax_exempt = null;
}
