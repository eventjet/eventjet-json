<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class IntegerRangeCollectionFields
{
    /** @var non-empty-list<int< -5, 5 >> */
    public array $bounded = [0];

    /** @var ArrayObject<string, int<min, max>> */
    public ArrayObject $map;

    /**
     * @param list<int<5,max>> $minimums
     * @param non-empty-array<string, int<min, -1>> $maximums
     */
    public function __construct(
        public array $minimums = [],
        public array $maximums = ['default' => -1],
    ) {
        $this->map = new ArrayObject();
    }
}
