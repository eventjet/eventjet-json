<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class UnionWithoutClasses
{
    /** @psalm-suppress PossiblyUnusedMethod */
    public function __construct(public string|int $val)
    {
    }
}
