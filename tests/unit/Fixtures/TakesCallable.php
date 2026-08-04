<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesCallable
{
    public mixed $value;

    public function __construct(callable $value)
    {
        $this->value = $value;
    }
}
