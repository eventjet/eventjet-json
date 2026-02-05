<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class OptionalFields
{
    public function __construct(
        public string $required,
        public string $optional = 'default',
    ) {
    }
}
