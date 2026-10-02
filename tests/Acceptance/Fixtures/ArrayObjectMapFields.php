<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class ArrayObjectMapFields
{
    /** @var ArrayObject<string, StringBackedStatus> */
    public ArrayObject $statuses;

    /**
     * @param ArrayObject<string, string> $strings
     * @param ArrayObject<string, Person> $people
     */
    public function __construct(
        public ArrayObject $strings,
        public ArrayObject $people,
    ) {
        /** @var ArrayObject<string, StringBackedStatus> */
        $statuses = new ArrayObject();
        $this->statuses = $statuses;
    }
}
