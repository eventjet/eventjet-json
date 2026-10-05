<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class PrefixedMapField
{
    /**
     * @param list<int> $value
     * @param non-empty-array<string, string> $values
     */
    public function __construct(
        public array $value,
        public array $values,
    ) {}
}
