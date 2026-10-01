<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NestedBackedEnumFields
{
    public function __construct(
        public BackedEnumFields $fields,
    ) {}
}
