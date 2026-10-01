<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use stdClass;

final readonly class StdClassField
{
    public function __construct(
        public stdClass $value,
    ) {}
}
