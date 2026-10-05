<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class TupleFields
{
    /** @var array{int, string, float, bool, StringBackedStatus, IntBackedStatus, Person} */
    public array $publicValue;

    /** @var array{} */
    public array $empty = [];

    /** @var array{int<-5, 5>} */
    public array $single = [0];

    /** @param array{int, string, float, bool, StringBackedStatus, IntBackedStatus, Person} $value */
    public function __construct(
        public array $value,
    ) {
        $this->publicValue = $value;
    }
}
