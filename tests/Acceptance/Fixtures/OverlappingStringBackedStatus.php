<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

enum OverlappingStringBackedStatus: string
{
    case Ready = 'ready';
    case Complete = 'complete';
}
