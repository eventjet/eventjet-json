<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Pattern;

final class ClassWithPattern
{
    public function __construct(
        #[Pattern('^[a-z]+$')]
        public string $slug,
    ) {
    }
}
