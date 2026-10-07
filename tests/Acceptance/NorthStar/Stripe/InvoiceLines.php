<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by acceptance tests. */
final readonly class InvoiceLines
{
    /** @param list<InvoiceLine> $data */
    public function __construct(
        public string $object,
        public array $data,
        public bool $has_more,
        public int $total_count,
        public string $url,
    ) {}
}
