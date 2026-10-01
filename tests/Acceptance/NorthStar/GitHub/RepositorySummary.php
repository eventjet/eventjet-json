<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final class RepositorySummary
{
    public function __construct(
        public int $id,
        public string $node_id,
        public string $name,
        public string $full_name,
        public User $owner,
    ) {}

    public bool $private = false;

    public string $html_url = '';

    public string|null $description = null;

    public bool $fork = false;

    public string $url = '';

    public string $default_branch = '';
}
