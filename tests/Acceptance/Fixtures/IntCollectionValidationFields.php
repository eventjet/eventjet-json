<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class IntCollectionValidationFields
{
    /** @var list<int> */
    public array $publicList;

    /** @var non-empty-list<int> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, int> */
    public array $publicMap;

    /** @var ArrayObject<string, int> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<int> $list
     * @param non-empty-list<int> $nonEmptyList
     * @param non-empty-array<string, int> $map
     * @param ArrayObject<string, int> $objectMap
     */
    public function __construct(
        public array $list = [1],
        public array $nonEmptyList = [1],
        public array $map = ['valid' => 1],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
