<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class RecursiveNode
{
    public function __construct(
        public string $value,
        public self|null $child,
    ) {}
}
