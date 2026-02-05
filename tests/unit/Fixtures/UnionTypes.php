<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class UnionTypes
{
    public function __construct(
        public string|int $value,
    ) {
    }
}
