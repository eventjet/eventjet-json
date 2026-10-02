<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class FinalClassMapFields
{
    /** @var array<array-key, Person> */
    public array $publicPeople = [];

    /** @param array<array-key, Person> $people */
    public function __construct(
        public array $people,
    ) {}
}
