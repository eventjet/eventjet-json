<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NonFinalClassMapField
{
    /** @param non-empty-array<string, ParentClassFieldBase> $values */
    public function __construct(
        public array $values,
    ) {}
}
