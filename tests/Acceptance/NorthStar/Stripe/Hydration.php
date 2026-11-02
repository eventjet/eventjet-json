<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

use Eventjet\Json\Test\Acceptance\NorthStar\HydrationCheck;

use function is_string;

/** @internal */
final class Hydration
{
    public static function isComplete(object $value): bool
    {
        if (!$value instanceof Invoice || !$value->lines instanceof InvoiceLines) {
            return false;
        }

        $firstLine = $value->lines->data[0] ?? null;
        $expandedDiscount = $value->discounts[0] ?? null;
        $discountReference = $value->discounts[1] ?? null;

        return (
            $value->status === InvoiceStatus::Open
            && $value->automatic_tax instanceof AutomaticTax
            && $value->customer instanceof Customer
            && $value->payment_intent instanceof PaymentIntent
            && $value->status_transitions instanceof StatusTransitions
            && HydrationCheck::allInstancesAre($value->lines->data, InvoiceLine::class)
            && $firstLine instanceof InvoiceLine
            && $firstLine->price instanceof Price
            && $expandedDiscount instanceof Discount
            && is_string($discountReference)
        );
    }
}
