<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class ZeroTagged
{
    public function __construct(
        public ZeroA|ZeroB $value,
    ) {}
}
