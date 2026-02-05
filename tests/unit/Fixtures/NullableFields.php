<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class NullableFields
{
    public function __construct(
        public string|null $name,
        public int|null $count,
    ) {
    }
}
