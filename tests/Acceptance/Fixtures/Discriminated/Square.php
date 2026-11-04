<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Square
{
    /** @var 1 */
    public int $version = 1;

    /** @param 'square'|'box' $type */
    public function __construct(
        public string $type,
        public int $side,
    ) {}
}
