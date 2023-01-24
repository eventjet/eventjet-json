<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesNonBackedEnum
{
    public function __construct(public readonly NonBackedEnum $status)
    {
    }
}
