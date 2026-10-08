<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class LiteralBooleanFields
{
    public function __construct(
        public true $true,
        public false $false,
    ) {}
}
