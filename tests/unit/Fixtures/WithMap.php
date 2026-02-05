<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithMap
{
    /**
     * @param array<string, int> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
