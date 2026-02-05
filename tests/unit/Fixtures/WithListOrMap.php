<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithListOrMap
{
    /**
     * @param list<string>|array<string, string> $data
     */
    public function __construct(
        public array $data,
    ) {
    }
}
