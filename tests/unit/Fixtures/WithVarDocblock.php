<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithVarDocblock
{
    /** @var list<string> */
    public array $tags;

    public function __construct()
    {
        $this->tags = [];
    }
}
