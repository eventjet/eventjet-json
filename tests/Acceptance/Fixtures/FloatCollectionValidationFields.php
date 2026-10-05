<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class FloatCollectionValidationFields
{
    /** @var list<float> */
    public array $publicList;

    /** @var non-empty-list<float> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, float> */
    public array $publicMap;

    /** @var ArrayObject<string, float> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<float> $list
     * @param non-empty-list<float> $nonEmptyList
     * @param non-empty-array<string, float> $map
     * @param ArrayObject<string, float> $objectMap
     */
    public function __construct(
        public array $list = [1.25],
        public array $nonEmptyList = [1.25],
        public array $map = ['valid' => 1.25],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
