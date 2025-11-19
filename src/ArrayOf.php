<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;
use Override;
use Stringable;

use function sprintf;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ArrayOf implements Stringable
{
    /**
     * @param 'string'|'int'|'float'|'null'|'bool'|class-string|self $itemType
     */
    public function __construct(public string|self $itemType)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('list<%s>', $this->itemType);
    }
}
