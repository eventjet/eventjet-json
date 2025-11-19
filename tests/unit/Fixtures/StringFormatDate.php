<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Format;

final readonly class StringFormatDate
{
    public function __construct(#[Format('date')] public string $date)
    {
    }
}
