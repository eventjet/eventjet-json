<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Triangle
{
    /** @var 1 */
    public int $version = 1;

    /** @param 'triangle'|'tri'|'delta' $type */
    public function __construct(
        public string $type,
        public int $base,
    ) {}
}
