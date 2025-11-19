<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Format
{
    public function __construct(public string $format)
    {
    }
}
