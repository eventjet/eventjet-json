<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class ClassScalarUnionField
{
    public function __construct(
        public Person|string|int|null $value,
    ) {}
}
