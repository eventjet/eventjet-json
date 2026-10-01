<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final class PaymentIntent extends PaymentIntentDefinition
{
    public string|null $description = null;
    public bool $livemode = false;
    /** @var array<string, string> */
    public array $metadata = [];
    public string|null $payment_method = null;
    /** @var list<string> */
    public array $payment_method_types = [];
    public string|null $receipt_email = null;
    public SetupFutureUsage|null $setup_future_usage = null;
    public Shipping|null $shipping = null;
    public string|null $statement_descriptor = null;
    public PaymentIntentStatus|null $status = null;
}
