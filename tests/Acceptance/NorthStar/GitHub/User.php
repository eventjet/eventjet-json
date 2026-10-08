<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
final class User
{
    public function __construct(
        public string $login,
        public int $id,
        public string $node_id,
        public string $avatar_url,
        public UserType $type,
    ) {}

    public bool $site_admin = false;
}
