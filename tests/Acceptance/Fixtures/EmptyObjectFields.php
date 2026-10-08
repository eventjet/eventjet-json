<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class EmptyObjectFields
{
    public EmptyObject $publicObject;

    /** @var list<EmptyObject> */
    public array $publicList = [];

    /** @var ArrayObject<string, EmptyObject> */
    public ArrayObject $publicMap;

    /**
     * @param list<EmptyObject> $list
     * @param ArrayObject<string, EmptyObject> $map
     */
    public function __construct(
        public EmptyObject $object = new EmptyObject(),
        public array $list = [],
        public ArrayObject $map = new ArrayObject(),
    ) {
        $this->publicObject = new EmptyObject();
        $this->publicMap = new ArrayObject();
    }
}
