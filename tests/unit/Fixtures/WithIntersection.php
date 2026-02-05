<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Countable;
use Iterator;

final class WithIntersection
{
    public function __construct(
        public Countable&Iterator $value,
    ) {
    }
}
