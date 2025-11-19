<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class TakesNonBackedEnum
{
    public function __construct(public NonBackedEnum $value)
    {
    }
}
