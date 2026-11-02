<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class CollectionUnionDefaults
{
    /** @var ArrayObject<string, int>|null */
    public ArrayObject|null $map = null;

    /** @param list<int>|string|null $value */
    public function __construct(
        public array|string|null $value = [0],
    ) {}
}
