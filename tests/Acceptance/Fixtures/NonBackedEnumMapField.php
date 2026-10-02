<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NonBackedEnumMapField
{
    /** @param array<string, NonBackedStatus> $values */
    public function __construct(
        public array $values,
    ) {}
}
