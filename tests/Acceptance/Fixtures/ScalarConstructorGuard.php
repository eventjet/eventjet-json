<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use RuntimeException;

final class ScalarConstructorGuard
{
    public static int $calls = 0;
    private static RuntimeException|null $exception = null;

    /** @throws RuntimeException */
    public function __construct(
        public int $value,
    ) {
        ++self::$calls;
        if ($value < 0) {
            throw self::exception();
        }
    }

    public static function exception(): RuntimeException
    {
        return self::$exception ??= new RuntimeException('Negative values are rejected.');
    }
}
