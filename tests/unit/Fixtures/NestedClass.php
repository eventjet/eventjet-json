<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class NestedClass
{
    public function __construct(
        public string $title,
        public SimpleClass $child,
    ) {
    }
}
