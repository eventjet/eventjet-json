<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class SelfCollectionNode
{
    /** @var list<self> */
    public array $publicList = [];

    /** @var ArrayObject<string, self> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<self> $children
     * @param ArrayObject<string, self> $namedChildren
     */
    public function __construct(
        public array $children = [],
        public ArrayObject $namedChildren = new ArrayObject(),
    ) {
        $this->publicObjectMap = new ArrayObject();
    }
}
