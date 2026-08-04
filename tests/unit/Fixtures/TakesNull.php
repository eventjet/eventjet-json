<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesNull
{
    public function __construct(public null $value = null)
    {
    }
}
