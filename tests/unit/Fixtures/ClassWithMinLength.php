<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\MinLength;

final class ClassWithMinLength
{
    public function __construct(
        #[MinLength(1)]
        public string $name,
    ) {
    }
}
