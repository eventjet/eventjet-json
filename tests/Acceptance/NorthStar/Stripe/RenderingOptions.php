<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class RenderingOptions
{
    public function __construct(
        public string|null $amount_tax_display,
        public string|null $template,
    ) {}
}
