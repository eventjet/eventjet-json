<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class RequiredString
{
    public function __construct(public string $name)
    {
    }
}
