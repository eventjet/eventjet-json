<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\UniqueItems;

final class ClassWithUniqueItems
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[UniqueItems]
        public array $tags,
    ) {
    }
}
