<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class StaticConstructorParameterProperty
{
    public static string $value;

    public function __construct(string $value)
    {
        self::$value = $value;
    }
}
