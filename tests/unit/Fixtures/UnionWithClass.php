<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class UnionWithClass
{
    public function __construct(
        public string|SimpleClass $data,
    ) {
    }
}
