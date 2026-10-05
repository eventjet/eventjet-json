<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class IndexedTupleFields
{
    /** @var array{0?: int, 1?: string, 2?: float, 3?: bool, 4?: StringBackedStatus, 5?: IntBackedStatus, 6?: Person} */
    public array $publicValue = [];

    /** @var array{0: int, 1: string} */
    public array $required = [42, 'text'];

    /** @var array{0: int, 1: string, 2?: int<-5, 5>} */
    public array $refined = [42, 'text'];

    /** @param array{0: Person, 1: string, 2?: int} $value */
    public function __construct(
        public array $value,
    ) {}
}
