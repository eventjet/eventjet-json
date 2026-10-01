<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final class TaxRate extends TaxRateDefinition
{
    public string|null $jurisdiction = null;
    public bool $livemode = false;
    /** @var array<string, string> */
    public array $metadata = [];
    public float $percentage = 0.0;
    public string|null $state = null;
    public string|null $tax_type = null;
}
