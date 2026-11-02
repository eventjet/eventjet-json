<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Countable;
use Iterator;

final readonly class NullableIntersectionTypeField
{
    public function __construct(
        public (Countable&Iterator)|null $value,
    ) {}
}
