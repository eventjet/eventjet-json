<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class ListType
{
    /** @param 'bool'|'float'|'int'|'string'|class-string $itemType */
    public function __construct(
        public string $itemType,
        public bool $nonEmpty,
    ) {}
}
