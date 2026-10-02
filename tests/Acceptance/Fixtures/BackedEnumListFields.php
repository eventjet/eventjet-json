<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class BackedEnumListFields
{
    /** @var list<\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus> */
    public array $publicStatuses = [];

    /**
     * @param list<StringBackedStatus> $stringStatuses
     * @param list<IntBackedStatus> $intStatuses
     */
    public function __construct(
        public array $stringStatuses,
        public array $intStatuses,
    ) {}
}
