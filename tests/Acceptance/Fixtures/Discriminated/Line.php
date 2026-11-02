<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Line
{
    /** @param ShapeKind::Line $kind */
    public function __construct(
        public ShapeKind $kind,
        public int $length,
    ) {}
}
