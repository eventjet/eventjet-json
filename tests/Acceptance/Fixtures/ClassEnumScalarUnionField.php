<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class ClassEnumScalarUnionField
{
    public function __construct(
        public Person|StringBackedStatus|int|null $value,
    ) {}
}
