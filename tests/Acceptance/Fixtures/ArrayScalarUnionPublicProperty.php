<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ArrayScalarUnionPublicProperty
{
    /** @var array<array-key, mixed>|string */
    public array|string $value = '';
}
