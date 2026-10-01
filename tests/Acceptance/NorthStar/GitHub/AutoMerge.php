<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class AutoMerge
{
    public function __construct(
        public User $enabled_by,
        public string $merge_method,
        public string $commit_title,
        public string $commit_message,
    ) {}
}
