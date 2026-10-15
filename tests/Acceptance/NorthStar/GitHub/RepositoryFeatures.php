<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
abstract class RepositoryFeatures extends RepositoryActivity
{
    public int $watchers_count = 0;
    public string|null $language = null;
    public bool $has_issues = false;
    public bool $has_projects = false;
    public bool $has_downloads = false;
    public bool $has_wiki = false;
    public bool $has_pages = false;
    public int $forks_count = 0;
    public bool $archived = false;
}
