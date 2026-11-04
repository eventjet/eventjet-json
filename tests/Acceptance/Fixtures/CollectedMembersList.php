<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

/** @internal */
final readonly class CollectedMembersList
{
    /** @param list<CollectedMembers> $items */
    public function __construct(
        public array $items,
    ) {}
}
