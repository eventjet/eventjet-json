<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

enum StringBackedEnum: string
{
    case Foo = 'yay';
    case Bar = 'nay';
}
