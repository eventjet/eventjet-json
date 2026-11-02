<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class OverlappingEnumUnionField
{
    public function __construct(
        public StringBackedStatus|OverlappingStringBackedStatus $value,
    ) {}
}
