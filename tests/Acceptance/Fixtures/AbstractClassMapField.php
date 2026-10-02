<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class AbstractClassMapField
{
    /** @param array<array-key, AbstractRootTarget> $values */
    public function __construct(
        public array $values,
    ) {}
}
