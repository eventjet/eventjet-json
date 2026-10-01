<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Stripe;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class InvoiceSettings
{
    /** @param list<CustomField> $custom_fields */
    public function __construct(
        public array $custom_fields,
        public string|null $default_payment_method,
        public string|null $footer,
        public RenderingOptions|null $rendering_options,
    ) {}
}
