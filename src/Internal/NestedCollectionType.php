<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;
use Stringable;

use function sprintf;

/** @internal */
final readonly class NestedCollectionType implements Stringable
{
    public function __construct(
        public ListType|MapType $collection,
        public bool $nullable,
    ) {}

    #[Override]
    public function __toString(): string
    {
        $collection = $this->collection;
        $name = $collection instanceof ListType
            ? sprintf('%s<%s>', $collection->nonEmpty ? 'non-empty-list' : 'list', $collection->itemType)
            : sprintf(
                '%s<string, %s>',
                $collection->arrayObject ? 'ArrayObject' : 'non-empty-array',
                $collection->valueType,
            );
        return $this->nullable ? $name . '|null' : $name;
    }
}
