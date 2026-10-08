<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class VariadicConstructorTarget
{
    /** @var array<array-key, string> */
    public array $values;

    public function __construct(string ...$values)
    {
        $this->values = $values;
    }
}
