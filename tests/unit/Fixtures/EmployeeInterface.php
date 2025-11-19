<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

interface EmployeeInterface
{
    /** @psalm-suppress PossiblyUnusedMethod */
    public function quit(): void;
}
