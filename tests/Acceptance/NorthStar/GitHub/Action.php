<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
enum Action: string
{
    case Opened = 'opened';
}
