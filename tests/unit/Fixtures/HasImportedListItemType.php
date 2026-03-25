<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Test\Unit\Json\Fixtures\SubNs\AliasedListItem as Aliased;
use Eventjet\Test\Unit\Json\Fixtures\SubNs\ImportedListItem;

final class HasImportedListItemType
{
    /**
     * @param list<ImportedListItem> $items1
     * @param list<ImportedListItem> $items2 The second set of items
     * @param list<Aliased> $items3 The third set of items
     */
    public function __construct(
        public readonly array $items1,
        public readonly array $items2,
        public readonly array $items3,
    ) {
    }
}
