<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class PrefixedMapField
{
    /** @param non-empty-array<string, string> $values */
    public function __construct(
        /** @var array<array-key, mixed> */
        public array $value,
        public array $values,
    ) {}
}
