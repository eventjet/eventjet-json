<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

use Eventjet\Json\Test\Acceptance\NorthStar\HydrationCheck;

/** @internal */
final class Hydration
{
    public static function isComplete(object $value): bool
    {
        if (!$value instanceof PullRequestEvent) {
            return false;
        }

        return (
            self::pullRequestIsComplete($value->pull_request)
            && self::repositoryIsComplete($value->repository)
            && $value->organization instanceof Organization
            && $value->installation instanceof Installation
        );
    }

    private static function pullRequestIsComplete(PullRequest $pullRequest): bool
    {
        return (
            $pullRequest->state instanceof PullRequestState
            && $pullRequest->user instanceof User
            && $pullRequest->head instanceof Branch
            && $pullRequest->base instanceof Branch
            && $pullRequest->_links instanceof PullRequestLinks
            && $pullRequest->author_association instanceof AuthorAssociation
            && HydrationCheck::allInstancesAre($pullRequest->labels, Label::class)
            && HydrationCheck::allInstancesAre($pullRequest->requested_reviewers, User::class)
        );
    }

    private static function repositoryIsComplete(Repository $repository): bool
    {
        return $repository->owner instanceof User && $repository->visibility instanceof RepositoryVisibility;
    }
}
