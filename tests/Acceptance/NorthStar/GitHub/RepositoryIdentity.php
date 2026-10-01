<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
abstract class RepositoryIdentity
{
    public int $id = 0;
    public string $node_id = '';
    public string $name = '';
    public string $full_name = '';
    public bool $private = false;
    public User|null $owner = null;
    public string $html_url = '';
    public string|null $description = null;
    public bool $fork = false;
    public string $url = '';
}
