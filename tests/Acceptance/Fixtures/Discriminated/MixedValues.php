<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

final class MixedValues
{
    /**
     * @param Circle|Rectangle|int|string|list<int>|null $value
     * @param list<Circle|Rectangle|StringBackedStatus|int|bool|list<int>|null> $values
     */
    public function __construct(
        public Circle|Rectangle|int|string|array|null $value,
        public array $values,
    ) {}
}
