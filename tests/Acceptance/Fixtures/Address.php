<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class Address
{
    public function __construct(
        public string $city,
        public Coordinates $coordinates,
    ) {}
}
