<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

enum IntBackedStatus: int
{
    case Ready = 1;
    case Pending = 0;
}
