<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Pong
{
    /** @param 2 $op */
    public function __construct(
        public int $op,
        public int $sequence,
    ) {}
}
