<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class DisjointStringBackedEnumUnionField
{
    public function __construct(
        public StringBackedStatus|StringBackedOutcome $value,
    ) {}
}
