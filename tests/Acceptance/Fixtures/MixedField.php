<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class MixedField
{
    public function __construct(
        public mixed $value,
    ) {}
}
