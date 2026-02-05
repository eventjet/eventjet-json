<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithMapOfList
{
    /**
     * @param array<string, list<int>> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
