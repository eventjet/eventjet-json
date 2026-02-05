<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class SimpleClass
{
    public function __construct(
        public string $name,
        public int $age,
        public float $score,
        public bool $active,
    ) {
    }
}
