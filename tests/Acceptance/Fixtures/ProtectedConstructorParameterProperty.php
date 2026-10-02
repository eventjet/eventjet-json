<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class ProtectedConstructorParameterProperty
{
    public string $encoded;

    public function __construct(
        protected string $value,
    ) {
        $this->encoded = $this->value;
    }
}
