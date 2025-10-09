<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class AllSimpleTypes
{
    public function __construct(
        public string $name,
        public int $age,
        public float $height,
        public bool $isActive,
        public true $yes,
        public false $no,
        public null $nothing,
    ) {
    }
}
