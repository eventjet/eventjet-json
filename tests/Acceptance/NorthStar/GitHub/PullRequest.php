<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final class PullRequest extends PullRequestMergeDetails
{
    public bool $merged = false;
    public int $comments = 0;
    public int $review_comments = 0;
    public bool $maintainer_can_modify = false;
    public int $commits = 0;
    public int $additions = 0;
    public int $deletions = 0;
    public int $changed_files = 0;
}
