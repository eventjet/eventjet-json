<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Throwable;

use function preg_replace;

/**
 * Errors for exceptions that other code throws while the decoder runs.
 *
 * @internal
 */
final class DecodeErrors
{
    /** Reports an error thrown by other code, such as a constructor, with its message and as the previous exception. */
    public static function thrown(DecodeErrorKind $kind, Throwable $error): DecodeError
    {
        $message = preg_replace(
            pattern: '/ passed in .+ on line \d+ and /',
            replacement: ' passed and ',
            subject: $error->getMessage(),
        ) ?? $error->getMessage();

        return new DecodeError($kind, $message, $error);
    }
}
