<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class MissingConstructorParameterProperty
{
    public string $encoded;

    public function __construct(string $value)
    {
        $this->encoded = $value;
    }
}
