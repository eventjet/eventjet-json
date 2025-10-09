<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class MultipleOptionalFields
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
    ) {
    }
}
