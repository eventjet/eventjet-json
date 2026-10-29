<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class PrivateConstructorTarget
{
    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }
}
