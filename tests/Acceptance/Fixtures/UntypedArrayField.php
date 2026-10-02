<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class UntypedArrayField
{
    public function __construct(
        /** @var array<array-key, mixed> */
        public array $value,
    ) {}
}
