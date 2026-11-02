<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final readonly class UnsupportedArrayObjectMapField
{
    /** @param ArrayObject<int, string> $values */
    public function __construct(
        public ArrayObject $values,
    ) {}
}
