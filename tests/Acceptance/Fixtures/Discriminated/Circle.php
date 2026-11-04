<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Circle
{
    /** @param 'circle' $type */
    public function __construct(
        public float $radius,
        public string $type = 'circle',
    ) {}
}
