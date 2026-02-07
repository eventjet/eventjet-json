<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithNullableDocblock
{
    /**
     * @param ?non-empty-string $name
     */
    public function __construct(
        public string|null $name,
    ) {
    }
}
