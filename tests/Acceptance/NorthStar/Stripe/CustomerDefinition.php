<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class CustomerDefinition
{
    public string $id = '';
    public string $object = '';
    public Address|null $address = null;
    public int $balance = 0;
    public int $created = 0;
    public Currency|null $currency = null;
    public string|null $default_source = null;
    public bool $delinquent = false;
    public string|null $description = null;
    public string|null $email = null;
}
