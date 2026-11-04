<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function sprintf;
use function var_export;

/**
 * Errors for JSON numbers that PHP cannot represent as the declared type.
 *
 * @internal
 */
final class InvalidNumber
{
    public static function nonFinite(string $path, float $value): DecodeError
    {
        return new DecodeError(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s must be a finite float. The JSON number is outside the PHP float range and decodes as %s, which json_encode() cannot write.',
            $path,
            var_export($value, return: true),
        ));
    }
}
