<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class IntEnumCollectionValidationFields
{
    /** @var list<IntBackedStatus> */
    public array $publicList;

    /** @var non-empty-list<IntBackedStatus> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, IntBackedStatus> */
    public array $publicMap;

    /** @var ArrayObject<string, IntBackedStatus> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<IntBackedStatus> $list
     * @param non-empty-list<IntBackedStatus> $nonEmptyList
     * @param non-empty-array<string, IntBackedStatus> $map
     * @param ArrayObject<string, IntBackedStatus> $objectMap
     */
    public function __construct(
        public array $list = [IntBackedStatus::Ready],
        public array $nonEmptyList = [IntBackedStatus::Ready],
        public array $map = ['valid' => IntBackedStatus::Ready],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
