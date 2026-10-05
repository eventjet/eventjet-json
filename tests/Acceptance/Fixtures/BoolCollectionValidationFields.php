<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class BoolCollectionValidationFields
{
    /** @var list<bool> */
    public array $publicList;

    /** @var non-empty-list<bool> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, bool> */
    public array $publicMap;

    /** @var ArrayObject<string, bool> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<bool> $list
     * @param non-empty-list<bool> $nonEmptyList
     * @param non-empty-array<string, bool> $map
     * @param ArrayObject<string, bool> $objectMap
     */
    public function __construct(
        public array $list = [true],
        public array $nonEmptyList = [true],
        public array $map = ['valid' => true],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
