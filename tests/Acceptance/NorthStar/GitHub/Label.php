<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\GitHub;

/** @api Consumed dynamically by NorthStarTest. */
final class Label
{
    public function __construct(
        public int $id,
        public string $node_id,
        public string $url,
        public string $name,
        public string $color,
    ) {}

    public bool $default = false;

    public string|null $description = null;
}
