<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OptionalStringDefaultNull
{
    public function __construct(public string|null $name = null)
    {
    }
}
