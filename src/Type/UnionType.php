<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

final readonly class UnionType implements ParsedType
{
    /**
     * @param list<ParsedType> $types
     */
    public function __construct(
        public array $types,
    ) {
    }
}
