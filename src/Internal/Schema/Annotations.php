<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use function max;

/**
 * The members and items of one value that a schema has evaluated so far, for `unevaluatedProperties` and
 * `unevaluatedItems`.
 *
 * @internal
 */
final class Annotations
{
    /** @var array<string, true> */
    public array $properties = [];

    /** How many leading items were evaluated. */
    public int $items = 0;

    public bool $allItems = false;

    /** @var array<array-key, true> Items that `contains` matched. */
    public array $indices = [];

    public function merge(self $other): void
    {
        $this->properties += $other->properties;
        $this->items = max($this->items, $other->items);
        $this->allItems = $this->allItems || $other->allItems;
        $this->indices += $other->indices;
    }
}
