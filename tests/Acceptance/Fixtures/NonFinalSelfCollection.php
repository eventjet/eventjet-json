<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

class NonFinalSelfCollection
{
    /** @param list<self> $children */
    public function __construct(
        public array $children = [],
    ) {}
}
