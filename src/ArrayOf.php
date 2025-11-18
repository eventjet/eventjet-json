<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ArrayOf
{
    /**
     * @param 'string'|'int'|'float'|'null'|'bool'|class-string|self $itemType
     */
    public function __construct(public string|self $itemType)
    {
    }
}
