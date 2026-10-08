<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class ScalarFields
{
    public function __construct(
        public string $string,
        public int $integer,
        public float $float,
        public bool $boolean,
    ) {}
}
