<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class DistinctEnumScalarUnionField
{
    public function __construct(
        public IntBackedStatus|string $value,
    ) {}
}
