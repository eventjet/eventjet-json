<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class FloatBoolUnion
{
    public function __construct(
        public float|bool $value,
    ) {
    }
}
