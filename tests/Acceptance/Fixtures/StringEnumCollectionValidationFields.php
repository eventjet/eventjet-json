<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class StringEnumCollectionValidationFields
{
    /** @var list<StringBackedStatus> */
    public array $publicList;

    /** @var non-empty-list<StringBackedStatus> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, StringBackedStatus> */
    public array $publicMap;

    /** @var ArrayObject<string, StringBackedStatus> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<StringBackedStatus> $list
     * @param non-empty-list<StringBackedStatus> $nonEmptyList
     * @param non-empty-array<string, StringBackedStatus> $map
     * @param ArrayObject<string, StringBackedStatus> $objectMap
     */
    public function __construct(
        public array $list = [StringBackedStatus::Ready],
        public array $nonEmptyList = [StringBackedStatus::Ready],
        public array $map = ['valid' => StringBackedStatus::Ready],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
