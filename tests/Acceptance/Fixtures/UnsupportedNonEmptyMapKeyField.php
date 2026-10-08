<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class UnsupportedNonEmptyMapKeyField
{
    /** @param non-empty-array<int, string> $values */
    public function __construct(
        public array $values,
    ) {}
}
