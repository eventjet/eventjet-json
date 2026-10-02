<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ArrayPublicProperty
{
    /** @var non-empty-array<string, int|list<array<string, int>>> */
    public array $value = ['default' => 0];
}
