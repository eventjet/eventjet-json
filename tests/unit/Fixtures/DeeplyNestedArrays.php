<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\ArrayOf;

final class DeeplyNestedArrays
{
    /**
     * @param list<list<list<string>>> $data
     */
    public function __construct(
        #[ArrayOf(new ArrayOf(new ArrayOf('string')))]
        public array $data,
    ) {
    }
}
