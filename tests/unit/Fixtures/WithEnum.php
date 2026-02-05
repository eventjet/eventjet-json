<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithEnum
{
    public function __construct(
        public string $name,
        public StringStatus $status,
    ) {
    }
}
