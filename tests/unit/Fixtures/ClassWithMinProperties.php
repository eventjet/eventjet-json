<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\MinProperties;

final class ClassWithMinProperties
{
    /**
     * @param array<string, int> $counts
     */
    public function __construct(
        #[MinProperties(1)]
        public array $counts,
    ) {
    }
}
