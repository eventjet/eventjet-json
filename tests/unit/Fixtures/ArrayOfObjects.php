<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\ArrayOf;

final class ArrayOfObjects
{
    /**
     * @param list<RequiredString> $items
     */
    public function __construct(#[ArrayOf(RequiredString::class)] public array $items)
    {
    }
}
