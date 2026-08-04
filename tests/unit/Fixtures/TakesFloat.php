<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesFloat
{
    public function __construct(public float $value = 0.0)
    {
    }
}
