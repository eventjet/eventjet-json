<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

enum StringBackedOutcome: string
{
    case Complete = 'complete';
    case Failed = 'failed';
}
