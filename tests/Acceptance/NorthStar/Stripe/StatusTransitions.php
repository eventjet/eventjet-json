<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final readonly class StatusTransitions
{
    public function __construct(
        public int|null $finalized_at,
        public int|null $marked_uncollectible_at,
        public int|null $paid_at,
        public int|null $voided_at,
    ) {}
}
