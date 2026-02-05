<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithListOfEnum
{
    /**
     * @param list<StringStatus> $statuses
     */
    public function __construct(
        public array $statuses,
    ) {
    }
}
