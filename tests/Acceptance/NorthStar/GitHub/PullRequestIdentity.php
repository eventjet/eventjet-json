<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
abstract class PullRequestIdentity
{
    public int $id = 0;
    public string $node_id = '';
    public int $number = 0;
    public PullRequestState|null $state = null;
    public bool $locked = false;
    public string $title = '';
    public User|null $user = null;
    public string|null $body = null;
    public string $created_at = '';
}
