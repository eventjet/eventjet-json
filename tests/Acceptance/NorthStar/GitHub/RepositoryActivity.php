<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
abstract class RepositoryActivity extends RepositoryIdentity
{
    public string $created_at = '';
    public string $updated_at = '';
    public string $pushed_at = '';
    public string $git_url = '';
    public string $ssh_url = '';
    public string $clone_url = '';
    public string|null $homepage = null;
    public int $size = 0;
    public int $stargazers_count = 0;
}
