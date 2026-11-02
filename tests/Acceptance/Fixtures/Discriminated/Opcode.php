<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

enum Opcode: int
{
    case Close = 8;
    case Unused = 15;
}
