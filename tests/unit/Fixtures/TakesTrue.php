<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesTrue
{
    public function __construct(public true $value = true)
    {
    }
}
