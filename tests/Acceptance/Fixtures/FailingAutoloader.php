<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use RuntimeException;

/**
 * An autoloader that fails for one class name with the same exception every time. The PHPUnit bootstrap registers it.
 *
 * @internal
 */
final class FailingAutoloader
{
    private static string $target = 'FailingAutoloadTarget';

    private static RuntimeException|null $failure = null;

    /** @return class-string */
    public static function target(): string
    {
        /** @var class-string */
        return self::$target;
    }

    public static function failure(): RuntimeException
    {
        return self::$failure ??= new RuntimeException('Autoloader failed.');
    }

    /** @throws RuntimeException */
    public static function autoload(string $class): void
    {
        if ($class === self::$target) {
            throw self::failure();
        }
    }
}
