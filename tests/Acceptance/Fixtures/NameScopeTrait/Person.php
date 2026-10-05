<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\NameScopeTrait;

use Eventjet\Json\Test\Acceptance\Fixtures\NameScopeTrait;

final readonly class Person
{
    use NameScopeTrait;

    public function __construct(
        public int $value,
    ) {}
}
