<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ArrayPublicProperty
{
    /** @var array<array-key, mixed> */
    public array $value = [];
}
