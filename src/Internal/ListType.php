<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class ListType
{
    /** @param 'bool'|'float'|'int'|'string'|class-string|CollectionUnionType|NestedCollectionType $itemType */
    public function __construct(
        public string|CollectionUnionType|NestedCollectionType $itemType,
        public bool $nonEmpty,
    ) {}
}
