<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Query
{
    public function __construct(
        public AndFilter|OrFilter|Equals $filter,
    ) {}
}
