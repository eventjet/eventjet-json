<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class FieldCollectionUnionType
{
    /** @param array<string, ListType|MapType|TupleType> $collections */
    public function __construct(
        public array $collections,
        public CollectionUnionType $members,
        public string $name,
    ) {}
}
