<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

enum ShapeKind: string
{
    case Dot = 'dot';
    case Line = 'line';
    case Polygon = 'polygon';
}
