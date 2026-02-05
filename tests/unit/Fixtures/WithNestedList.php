<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithNestedList
{
    /**
     * @param list<list<int>> $matrix
     */
    public function __construct(
        public array $matrix,
    ) {
    }
}
