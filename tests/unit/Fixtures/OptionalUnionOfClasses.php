<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OptionalUnionOfClasses
{
    public function __construct(
        public OptionalIntegerWithDefaultInt|OptionalStringDefaultNull|null $obj = null,
    ) {
    }
}
