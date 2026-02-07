<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithNonVarDocblock
{
    /** Some description but no var tag. */
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
