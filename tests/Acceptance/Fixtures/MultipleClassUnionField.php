<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class MultipleClassUnionField
{
    public function __construct(
        public ScalarFields|Person|string $value,
    ) {}
}
