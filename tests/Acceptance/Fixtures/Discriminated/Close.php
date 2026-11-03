<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Close
{
    /** @param Opcode::Close $op */
    public function __construct(
        public Opcode $op,
        public string $reason,
    ) {}
}
