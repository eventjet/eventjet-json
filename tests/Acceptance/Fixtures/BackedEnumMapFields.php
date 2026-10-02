<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class BackedEnumMapFields
{
    /** @var array<string, StringBackedStatus> */
    public array $publicStatuses = [];

    /**
     * @param array<string, StringBackedStatus> $stringStatuses
     * @param array<string, IntBackedStatus> $intStatuses
     */
    public function __construct(
        public array $stringStatuses,
        public array $intStatuses,
    ) {}
}
