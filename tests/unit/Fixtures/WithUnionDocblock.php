<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithUnionDocblock
{
    /**
     * @param string|int $value
     */
    public function __construct(
        public mixed $value,
    ) {
    }
}
