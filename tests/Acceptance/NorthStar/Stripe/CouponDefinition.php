<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
abstract class CouponDefinition
{
    public string $id = '';
    public string $object = '';
    public int|null $amount_off = null;
    public Currency|null $currency = null;
    public CouponDuration|null $duration = null;
    public int|null $duration_in_months = null;
    public bool $livemode = false;
}
