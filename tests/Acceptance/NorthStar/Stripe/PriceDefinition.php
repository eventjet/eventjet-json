<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
abstract class PriceDefinition
{
    public string $id = '';
    public string $object = '';
    public bool $active = false;
    public BillingScheme|null $billing_scheme = null;
    public Currency|null $currency = null;
    public bool $livemode = false;
    public string|null $lookup_key = null;
    /** @var array<string, string> */
    public array $metadata = [];
}
