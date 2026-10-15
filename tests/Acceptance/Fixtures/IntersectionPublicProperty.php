<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayIterator;
use Countable;
use Iterator;

final class IntersectionPublicProperty
{
    public Countable&Iterator $value;

    public function __construct()
    {
        $this->value = new ArrayIterator([]);
    }
}
