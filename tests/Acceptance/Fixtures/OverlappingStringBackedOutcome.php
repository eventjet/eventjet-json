<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

enum OverlappingStringBackedOutcome: string
{
    case Started = 'started';
    case Failed = 'failed';
}
