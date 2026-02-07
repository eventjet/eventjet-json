<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class SelfReferencing
{
    public function __construct(
        public string $name,
        public SelfReferencing|null $child,
    ) {
    }
}
