<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

/** @internal */
final readonly class CompiledHydrationTarget
{
    public function __construct(
        public int $value,
    ) {}
}
