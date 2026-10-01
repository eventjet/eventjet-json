<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class DistinctStringEnumScalarUnionField
{
    public function __construct(
        public StringBackedStatus|int $value,
    ) {}
}
