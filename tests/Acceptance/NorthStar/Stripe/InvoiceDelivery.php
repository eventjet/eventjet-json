<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class InvoiceDelivery extends InvoiceCustomerDetails
{
    public string|null $hosted_invoice_url = null;
    public string|null $invoice_pdf = null;
    public InvoiceLines|null $lines = null;
    public PaymentIntent|string|null $payment_intent = null;
    public ShippingCost|null $shipping_cost = null;
    public string|null $statement_descriptor = null;
    public InvoiceStatus|null $status = null;
    public StatusTransitions|null $status_transitions = null;
    public string|null $test_clock = null;
}
