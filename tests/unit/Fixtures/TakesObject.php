<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesObject
{
    public function __construct(public object $value)
    {
    }
}
