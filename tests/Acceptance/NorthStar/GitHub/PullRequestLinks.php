<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final class PullRequestLinks
{
    public function __construct(
        public Link $self,
        public Link $html,
        public Link $issue,
        public Link $comments,
        public Link $review_comments,
    ) {}

    public Link|null $commits = null;

    public Link|null $statuses = null;
}
