<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
enum RepositoryVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Internal = 'internal';
}
