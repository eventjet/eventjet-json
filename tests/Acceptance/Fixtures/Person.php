<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class Person
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string|null $middleName = null,
        public int|null $age = null,
    ) {}
}
