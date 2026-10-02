<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ArrayPublicProperty
{
    /** @var array<string, int|list<array<string, int>>> */
    public array $value = [];
}
