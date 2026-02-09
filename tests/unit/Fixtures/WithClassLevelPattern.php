<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithClassLevelPattern
{
    public function __construct(
        public ClassWithClassLevelPattern $id,
    ) {
    }
}
