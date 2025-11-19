<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class TakesStringEnum
{
    public function __construct(public StringBackedEnum $val)
    {
    }
}
