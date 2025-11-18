<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\ArrayOf;

final readonly class ArrayOfStrings
{
    /**
     * @param list<string> $strings
     */
    public function __construct(#[ArrayOf('string')] public array $strings)
    {
    }
}
