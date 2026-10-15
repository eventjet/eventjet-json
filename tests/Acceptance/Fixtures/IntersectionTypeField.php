<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Countable;
use Iterator;

final readonly class IntersectionTypeField
{
    public function __construct(
        public Countable&Iterator $value,
    ) {}
}
