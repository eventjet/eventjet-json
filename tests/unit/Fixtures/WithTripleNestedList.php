<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithTripleNestedList
{
    /**
     * @param list<list<list<string>>> $data
     */
    public function __construct(
        public array $data,
    ) {
    }
}
