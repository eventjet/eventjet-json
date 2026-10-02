<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class AmbiguousMapField
{
    /** @param array<string, string> $values */
    public function __construct(
        public array $values,
    ) {}
}
