<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Countable;

final class WithInterface
{
    public function __construct(
        public Countable $value,
    ) {
    }
}
