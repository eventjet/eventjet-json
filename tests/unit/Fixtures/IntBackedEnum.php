<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

enum IntBackedEnum: int
{
    case Foo = 42;
    case Bar = 69;
}
