<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithListOfClass
{
    /**
     * @param list<SimpleClass> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
