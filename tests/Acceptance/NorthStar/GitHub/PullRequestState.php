<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
enum PullRequestState: string
{
    case Open = 'open';
    case Closed = 'closed';
}
