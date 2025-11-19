<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class MissingConstructorArgumentType
{
    /**
     * @psalm-suppress MissingParamType
     * @phpstan-ignore-next-line missingType.parameter
     */
    public function __construct(public $val)
    {
    }
}
