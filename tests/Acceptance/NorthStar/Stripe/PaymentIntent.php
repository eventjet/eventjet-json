<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class PaymentIntent extends PaymentIntentDefinition
{
    public string|null $description = null;
    public bool $livemode = false;
    public string|null $payment_method = null;
    /** @var list<string> */
    public array $payment_method_types = [];
    public string|null $receipt_email = null;
    public SetupFutureUsage|null $setup_future_usage = null;
    public Shipping|null $shipping = null;
    public string|null $statement_descriptor = null;
    public PaymentIntentStatus|null $status = null;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
