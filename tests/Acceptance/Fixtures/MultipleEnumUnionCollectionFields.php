<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class MultipleEnumUnionCollectionFields
{
    /** @var non-empty-list<MultipleEnumUnionPublicProperty> */
    public array $publicList;

    /** @var ArrayObject<string, DisjointStringBackedEnumUnionPublicProperty> */
    public ArrayObject $publicMap;

    /**
     * @param list<MultipleEnumUnionField> $items
     * @param non-empty-array<string, DisjointStringBackedEnumUnionField> $named
     */
    public function __construct(
        public array $items,
        public array $named,
    ) {
        $this->publicList = [new MultipleEnumUnionPublicProperty()];
        $this->publicMap = new ArrayObject();
    }
}
