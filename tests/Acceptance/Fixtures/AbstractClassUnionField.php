<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

/** @internal */
final readonly class AbstractClassUnionField
{
    public function __construct(
        public AbstractRootTarget|string $value,
    ) {}
}
