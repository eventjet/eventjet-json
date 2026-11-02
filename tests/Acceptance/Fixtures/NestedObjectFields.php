<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NestedObjectFields
{
    public function __construct(
        public Person $person,
        public Address $address,
        public Person|null $alternate,
    ) {}
}
