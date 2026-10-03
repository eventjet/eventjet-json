<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class NonEmptyListFields
{
    /** @var non-empty-list<string> */
    public array $labels = ['default'];

    /** @param non-empty-list<int> $values */
    public function __construct(
        public array $values,
    ) {}
}
