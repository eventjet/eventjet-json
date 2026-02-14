<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OneOfVariantA implements OneOfInterface
{
    public function __construct(
        public string $alpha,
    ) {
    }
}
