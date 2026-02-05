<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class ClassUnion
{
    public function __construct(
        public SimpleClass|NestedClass $data,
    ) {
    }
}
