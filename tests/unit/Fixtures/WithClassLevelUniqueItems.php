<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithClassLevelUniqueItems
{
    public function __construct(
        public ClassWithClassLevelUniqueItems $tags,
    ) {
    }
}
