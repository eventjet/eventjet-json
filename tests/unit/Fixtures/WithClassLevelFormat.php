<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithClassLevelFormat
{
    public function __construct(
        public ClassWithClassLevelFormat $id,
    ) {
    }
}
