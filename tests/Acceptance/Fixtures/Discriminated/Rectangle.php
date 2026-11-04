<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Rectangle
{
    /** @var 'rectangle' */
    public string $type = 'rectangle';

    public function __construct(
        public int $width,
        public int $height,
    ) {}
}
