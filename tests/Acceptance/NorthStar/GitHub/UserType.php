<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
enum UserType: string
{
    case User = 'User';
    case Bot = 'Bot';
    case Organization = 'Organization';
}
