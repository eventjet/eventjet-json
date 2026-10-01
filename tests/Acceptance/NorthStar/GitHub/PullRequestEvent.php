<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final class PullRequestEvent
{
    public function __construct(
        public Action $action,
        public int $number,
        public PullRequest $pull_request,
        public Repository $repository,
        public User $sender,
    ) {}

    public Organization|null $organization = null;

    public Installation|null $installation = null;

    public Enterprise|null $enterprise = null;
}
