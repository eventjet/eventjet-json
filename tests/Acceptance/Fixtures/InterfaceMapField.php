<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class InterfaceMapField
{
    /** @param array<string, RootTargetInterface> $values */
    public function __construct(
        public array $values,
    ) {}
}
