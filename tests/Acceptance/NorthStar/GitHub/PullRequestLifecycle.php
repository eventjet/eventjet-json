<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
abstract class PullRequestLifecycle extends PullRequestIdentity
{
    public string $updated_at = '';
    public string|null $closed_at = null;
    public string|null $merged_at = null;
    public string|null $merge_commit_sha = null;
    public bool $draft = false;
    /** @var list<User> */
    public array $requested_reviewers = [];
    /** @var list<Team> */
    public array $requested_teams = [];
    /** @var list<Label> */
    public array $labels = [];
    public Branch|null $head = null;
}
