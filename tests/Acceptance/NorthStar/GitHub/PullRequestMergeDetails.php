<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by acceptance tests. */
abstract class PullRequestMergeDetails extends PullRequestLifecycle
{
    public Branch|null $base = null;
    public PullRequestLinks|null $_links = null;
    public AuthorAssociation|null $author_association = null;
    public AutoMerge|null $auto_merge = null;
    public string|null $active_lock_reason = null;
    public bool|null $mergeable = null;
    public bool|null $rebaseable = null;
    public string $mergeable_state = '';
}
