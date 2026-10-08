<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class CombinedCollectionFields
{
    /** @var ArrayObject<string, BackedEnumFields> */
    public ArrayObject $publicMap;

    /**
     * @param list<BackedEnumFields> $items
     * @param non-empty-array<string, BackedEnumFields> $named
     */
    public function __construct(
        public BackedEnumFields $nested,
        public array $items,
        public array $named,
    ) {
        $this->publicMap = new ArrayObject();
    }
}
