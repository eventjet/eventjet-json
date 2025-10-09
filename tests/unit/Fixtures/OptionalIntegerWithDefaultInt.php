<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OptionalIntegerWithDefaultInt
{
    public function __construct(public int $age = 42)
    {
    }
}
