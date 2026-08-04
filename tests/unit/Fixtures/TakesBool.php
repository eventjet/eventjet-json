<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesBool
{
    public function __construct(public bool $value = false)
    {
    }
}
