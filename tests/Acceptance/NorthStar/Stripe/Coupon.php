<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final class Coupon extends CouponDefinition
{
    public int|null $max_redemptions = null;
    /** @var array<string, string> */
    public array $metadata = [];
    public string|null $name = null;
    public float|null $percent_off = null;
    public int|null $redeem_by = null;
    public int $times_redeemed = 0;
    public bool $valid = false;
}
