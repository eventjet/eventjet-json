<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class RefinedIntegerCollectionFields
{
    /** @var non-empty-list<non-negative-int> */
    public array $nonNegative = [0];

    /** @var ArrayObject<string, non-positive-int> */
    public ArrayObject $nonPositive;

    /**
     * @param list<positive-int> $positive
     * @param non-empty-array<string, negative-int> $negative
     */
    public function __construct(
        public array $positive = [],
        public array $negative = ['default' => -1],
    ) {
        $this->nonPositive = new ArrayObject();
    }
}
