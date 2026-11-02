<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
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
    /** @var ArrayObject<string, bool> */
    public ArrayObject $permissions;

    public function __construct()
    {
        /** @var ArrayObject<string, bool> $permissions */
        $permissions = new ArrayObject();
        $this->permissions = $permissions;
    }
}
