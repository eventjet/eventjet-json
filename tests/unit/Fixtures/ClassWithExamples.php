<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Example;

#[Example(['name' => 'John', 'age' => 30])]
#[Example(['name' => 'Jane', 'age' => 25])]
final class ClassWithExamples
{
    public function __construct(
        public string $name,
        public int $age,
    ) {
    }
}
