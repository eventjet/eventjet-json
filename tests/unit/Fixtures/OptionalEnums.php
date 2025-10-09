<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OptionalEnums
{
    public function __construct(
        public StringBackedEnum|null $str = null,
        public IntBackedEnum|null $int = null,
        public NonBackedEnum|null $nb = null,
    ) {
    }
}
