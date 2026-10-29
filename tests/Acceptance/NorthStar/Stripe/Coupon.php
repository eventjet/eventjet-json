<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class Coupon extends CouponDefinition
{
    public int|null $max_redemptions = null;
    public string|null $name = null;
    public float|null $percent_off = null;
    public int|null $redeem_by = null;
    public int $times_redeemed = 0;
    public bool $valid = false;

    /** @param ArrayObject<string, string> $metadata */
    public function __construct(
        public ArrayObject $metadata = new ArrayObject(),
    ) {}
}
