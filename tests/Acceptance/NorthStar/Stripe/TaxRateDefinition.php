<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class TaxRateDefinition
{
    public string $id = '';
    public string $object = '';
    public bool $active = false;
    public string|null $country = null;
    public string|null $description = null;
    public string $display_name = '';
    public bool $inclusive = false;
}
