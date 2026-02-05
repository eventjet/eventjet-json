<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

final readonly class MapType implements ParsedType
{
    public function __construct(
        public PrimitiveType $keyType,
        public ParsedType $valueType,
    ) {
    }
}
