<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Aliases
{
    /** @param list<Square|Triangle> $shapes */
    public function __construct(
        public array $shapes,
    ) {}
}
