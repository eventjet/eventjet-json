<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final class Price extends PriceDefinition
{
    public string|null $nickname = null;
    public string $product = '';
    public PriceRecurring|null $recurring = null;
    public TaxBehavior|null $tax_behavior = null;
    public PriceType|null $type = null;
    public int|null $unit_amount = null;
    public string|null $unit_amount_decimal = null;
}
