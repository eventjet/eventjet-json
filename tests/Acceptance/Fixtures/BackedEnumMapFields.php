<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class BackedEnumMapFields
{
    /** @var non-empty-array<string, StringBackedStatus> */
    public array $publicStatuses;

    /**
     * @param non-empty-array<string, StringBackedStatus> $stringStatuses
     * @param non-empty-array<string, IntBackedStatus> $intStatuses
     */
    public function __construct(
        public array $stringStatuses,
        public array $intStatuses,
    ) {
        $this->publicStatuses = ['default' => StringBackedStatus::Ready];
    }
}
