<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ProtectedConstructorTarget extends ProtectedConstructorTargetBase
{
    public static function create(): self
    {
        return new self();
    }
}
