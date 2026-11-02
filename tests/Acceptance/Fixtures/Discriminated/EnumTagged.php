<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class EnumTagged
{
    /**
     * @param list<Dot|Line|Label> $marks
     * @param list<Ping|Pong|Close> $frames
     */
    public function __construct(
        public array $marks,
        public array $frames,
    ) {}
}
