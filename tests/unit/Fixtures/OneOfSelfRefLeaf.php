<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OneOfSelfRefLeaf implements OneOfSelfRef
{
    public function __construct(
        public string $value,
    ) {
    }
}
