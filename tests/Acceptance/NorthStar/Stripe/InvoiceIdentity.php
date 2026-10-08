<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class InvoiceIdentity
{
    public string $id = '';
    public string $object = '';
    public string|null $account_country = null;
    public string|null $account_name = null;
    public int $created = 0;
    public Currency|null $currency = null;
    public string|null $number = null;
    public bool $livemode = false;
    public string|null $description = null;
}
