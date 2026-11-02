<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Codes
{
    /** @param list<NumericCode|TextCode> $codes */
    public function __construct(
        public array $codes,
    ) {}
}
