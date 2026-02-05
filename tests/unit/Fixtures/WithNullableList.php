<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithNullableList
{
    /**
     * @param list<string>|null $items
     */
    public function __construct(
        public array|null $items,
    ) {
    }
}
