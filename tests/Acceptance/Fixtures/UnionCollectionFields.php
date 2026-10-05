<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class UnionCollectionFields
{
    /** @var ArrayObject<string, ClassEnumScalarUnionPublicProperty> */
    public ArrayObject $publicMap;

    /**
     * @param list<ClassEnumScalarUnionField> $items
     * @param non-empty-array<string, ClassEnumScalarUnionPublicProperty> $named
     */
    public function __construct(
        public array $items,
        public array $named,
    ) {
        $this->publicMap = new ArrayObject();
    }
}
