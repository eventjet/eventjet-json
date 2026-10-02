<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NonFinalClassListField
{
    /** @param list<ParentClassFieldBase> $values */
    public function __construct(
        public array $values,
    ) {}
}
