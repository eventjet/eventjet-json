<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesMixed
{
    public function __construct(public mixed $value = null)
    {
    }
}
