<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesInt
{
    public function __construct(public int $value = 0)
    {
    }
}
