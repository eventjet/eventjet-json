<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class MapType
{
    /** @param 'bool'|'float'|'int'|'string'|class-string|CollectionUnionType $valueType */
    public function __construct(
        public string|CollectionUnionType $valueType,
        public bool $arrayObject,
    ) {}
}
