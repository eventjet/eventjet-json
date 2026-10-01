<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final class Repository extends RepositoryFeatures
{
    public bool $disabled = false;
    public int $open_issues_count = 0;
    public License|null $license = null;
    public bool $allow_forking = false;
    public bool $is_template = false;
    /** @var list<string> */
    public array $topics = [];
    public RepositoryVisibility|null $visibility = null;
    public string $default_branch = '';
    /** @var array<string, bool> */
    public array $permissions = [];
}
