<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class FinalClassMapFields
{
    /** @var non-empty-array<string, Person> */
    public array $publicPeople;

    /** @param non-empty-array<string, Person> $people */
    public function __construct(
        public array $people,
    ) {
        $this->publicPeople = ['default' => new Person('Default', 'Person')];
    }
}
