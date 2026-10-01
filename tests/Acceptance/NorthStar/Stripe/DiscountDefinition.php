<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
abstract class DiscountDefinition
{
    public string $id = '';
    public string $object = '';
    public string|null $checkout_session = null;
    public Coupon|null $coupon = null;
    public Customer|string|null $customer = null;
    public int|null $end = null;
}
