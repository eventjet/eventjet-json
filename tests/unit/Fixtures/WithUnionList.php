<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithUnionList
{
    /**
     * @param string|list<string> $data
     */
    public function __construct(
        public string|array $data,
    ) {
    }
}
