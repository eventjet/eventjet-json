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
    public static function missing(string $member, string $allowed): DecodeError
    {
        return new DecodeError(
            DecodeErrorKind::InvalidValue,
            sprintf('has no member %s, which must be one of %s.', $member, $allowed),
            true,
        );
    }

    public static function type(string $allowed, mixed $value): DecodeError
    {
        return new DecodeError(
            DecodeErrorKind::InvalidValue,
            sprintf('must be one of %s; %s given.', $allowed, get_debug_type($value)),
            true,
        );
    }

    public static function unknown(string $allowed, int|string $value): DecodeError
    {
        return new DecodeError(
            DecodeErrorKind::InvalidValue,
            sprintf('must be one of %s; %s given.', $allowed, var_export($value, return: true)),
            true,
        );
    }
}
