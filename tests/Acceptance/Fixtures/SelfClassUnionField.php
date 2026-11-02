<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class SelfClassUnionField
{
    public function __construct(
        public self|Person $value,
    ) {}
}
