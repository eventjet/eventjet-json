<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use RuntimeException;

final class ThrowingConstructor
{
    private static RuntimeException|null $exception = null;

    /** @throws RuntimeException */
    public function __construct(
        public string $value,
    ) {
        if ($value === 'rejected') {
            throw self::exception();
        }
    }

    public static function exception(): RuntimeException
    {
        return self::$exception ??= new RuntimeException('The constructor rejected the decoded value.');
    }
}
