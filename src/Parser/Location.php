<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

final class Location
{
    public function __construct(public readonly int $line, public readonly int $column)
    {
    }
}
