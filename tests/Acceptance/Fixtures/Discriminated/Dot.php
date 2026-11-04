<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Dot
{
    /** @param ShapeKind::Dot $kind */
    public function __construct(
        public ShapeKind $kind,
        public int $x,
    ) {}
}
