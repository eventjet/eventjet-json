<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OptionalMixed
{
    public function __construct(public mixed $val = null)
    {
    }
}
