<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class MapHolder
{
    /** @param non-empty-array<string, int> $map */
    public function __construct(
        public array $map,
    ) {}
}
