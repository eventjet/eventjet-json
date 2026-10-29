<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject as Map;

final class NestedSelfCollections
{
    /** @var Map<string, Map<string, self|null>> */
    public Map $maps;

    /** @param list<list<self>> $lists */
    public function __construct(
        public array $lists = [],
    ) {
        $this->maps = new Map();
    }
}
