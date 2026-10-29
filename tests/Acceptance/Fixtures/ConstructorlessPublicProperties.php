<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ConstructorlessPublicProperties
{
    public string $string = '';
    public int $integer = 0;
    public float $float = 0.0;
    public bool $boolean = false;
    public string|null $nullable = null;
}
