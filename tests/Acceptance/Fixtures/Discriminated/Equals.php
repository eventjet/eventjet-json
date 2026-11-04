<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Equals
{
    /** @param 'equals' $type */
    public function __construct(
        public string $type,
        public string $field,
        public string $value,
    ) {}
}
