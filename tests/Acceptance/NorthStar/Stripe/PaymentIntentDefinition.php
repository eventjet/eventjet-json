<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
abstract class PaymentIntentDefinition
{
    public string $id = '';
    public string $object = '';
    public int $amount = 0;
    public int $amount_capturable = 0;
    public int $amount_received = 0;
    public CaptureMethod|null $capture_method = null;
    public ConfirmationMethod|null $confirmation_method = null;
    public int $created = 0;
    public Currency|null $currency = null;
    public Customer|string|null $customer = null;
}
