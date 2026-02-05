<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class UntypedParam
{
    /** @param mixed $value */
    public function __construct(
        public $value,
    ) {
    }
}
