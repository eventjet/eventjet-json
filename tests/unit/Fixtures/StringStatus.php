<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

enum StringStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}
