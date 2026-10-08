<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final class Address
{
    public string|null $city = null;

    public string|null $country = null;

    public string|null $line1 = null;

    public string|null $line2 = null;

    public string|null $postal_code = null;

    public string|null $state = null;
}
