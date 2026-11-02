<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
final readonly class Branch
{
    public function __construct(
        public string $label,
        public string $ref,
        public string $sha,
        public User $user,
        public RepositorySummary $repo,
    ) {}
}
