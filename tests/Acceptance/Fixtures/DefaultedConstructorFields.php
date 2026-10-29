<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class DefaultedConstructorFields
{
    public function __construct(
        public string $required,
        public string $label = 'default label',
        public int|null $nullableCount = 42,
    ) {}
}
