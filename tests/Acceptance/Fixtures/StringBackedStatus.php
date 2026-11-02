<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

enum StringBackedStatus: string
{
    case Ready = 'ready';
    case Pending = 'pending';
}
