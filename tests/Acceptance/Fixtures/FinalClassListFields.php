<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class FinalClassListFields
{
    /** @var list<Person> */
    public array $publicPeople = [];

    /** @param list<Person> $people */
    public function __construct(
        public array $people,
    ) {}
}
