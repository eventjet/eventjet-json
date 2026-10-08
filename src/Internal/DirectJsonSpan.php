<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal A container's validated source interval. */
final readonly class DirectJsonSpan
{
    public function __construct(
        public int $start,
        public int $end,
        public string $kind,
    ) {}
}
