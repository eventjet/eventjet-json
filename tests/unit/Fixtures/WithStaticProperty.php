<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithStaticProperty
{
    public static int $counter = 0;

    public function __construct(
        public string $name,
    ) {
    }
}
