<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class NullableScalarFields
{
    public function __construct(
        public string|null $string,
        public int|null $integer,
        public float|null $float,
        public bool|null $boolean,
        public null $null,
    ) {}
}
