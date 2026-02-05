<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithNestedMap
{
    /**
     * @param array<string, array<string, int>> $matrix
     */
    public function __construct(
        public array $matrix,
    ) {
    }
}
