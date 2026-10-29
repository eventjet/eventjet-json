<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class MultipleEnumUnionField
{
    public function __construct(
        public StringBackedStatus|IntBackedStatus $value,
    ) {}
}
