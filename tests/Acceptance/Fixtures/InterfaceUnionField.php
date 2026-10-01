<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

/** @internal */
final readonly class InterfaceUnionField
{
    public function __construct(
        public RootTargetInterface|string $value,
    ) {}
}
