<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

enum DeferredValueEnum: string
{
    case Ready = DeferredEnumBacking::VALUE;
}
