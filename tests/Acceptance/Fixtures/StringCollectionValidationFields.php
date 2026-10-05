<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class StringCollectionValidationFields
{
    /** @var list<string> */
    public array $publicList;

    /** @var non-empty-list<string> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, string> */
    public array $publicMap;

    /** @var ArrayObject<string, string> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<string> $list
     * @param non-empty-list<string> $nonEmptyList
     * @param non-empty-array<string, string> $map
     * @param ArrayObject<string, string> $objectMap
     */
    public function __construct(
        public array $list = ['ready'],
        public array $nonEmptyList = ['ready'],
        public array $map = ['valid' => 'ready'],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
