<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesFalse
{
    public function __construct(public false $value = false)
    {
    }
}
