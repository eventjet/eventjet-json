<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OptionalObject
{
    public function __construct(public object|null $obj = null)
    {
    }
}
