<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class InterfaceListField
{
    /** @param list<RootTargetInterface> $values */
    public function __construct(
        public array $values,
    ) {}
}
