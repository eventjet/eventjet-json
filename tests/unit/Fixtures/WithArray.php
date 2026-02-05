<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithArray
{
    /**
     * @param array<string> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
