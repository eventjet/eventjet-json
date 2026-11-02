<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NonBackedEnumUnionField
{
    public function __construct(
        public NonBackedStatus|string $value,
    ) {}
}
