<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

/** @internal */
final readonly class MappedBracketHolder
{
    public function __construct(
        public MappedBracketReference $reference,
    ) {}
}
