<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function get_debug_type;
use function sprintf;
use function var_export;

/**
 * Errors for JSON objects whose discriminator selects no class of a union. Each lists the values that select one.
 *
 * @internal
 */
final class InvalidDiscriminator
{
    public static function missing(string $path, string $member, string $allowed): DecodeError
    {
        return self::error(sprintf('Field %s has no member %s, which must be one of %s.', $path, $member, $allowed));
    }

    public static function type(string $path, string $allowed, mixed $value): DecodeError
    {
        return self::error(sprintf('Field %s must be one of %s, %s given.', $path, $allowed, get_debug_type($value)));
    }

    public static function unknown(string $path, string $allowed, int|string $value): DecodeError
    {
        return self::error(sprintf(
            'Field %s must be one of %s, %s given.',
            $path,
            $allowed,
            var_export($value, return: true),
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::InvalidValue, $message);
    }
}
