<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NonBackedEnumField
{
    public function __construct(
        public NonBackedStatus $status,
    ) {}
}
