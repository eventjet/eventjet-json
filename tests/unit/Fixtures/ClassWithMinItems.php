<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\MinItems;

final class ClassWithMinItems
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[MinItems(1)]
        public array $tags,
    ) {
    }
}
