<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OneOfVariantB implements OneOfInterface
{
    public function __construct(
        public int $beta,
    ) {
    }
}
