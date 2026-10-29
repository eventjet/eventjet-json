<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function get_debug_type;
use function sprintf;

/**
 * Errors for documents that fail before any class is involved.
 *
 * @internal
 */
final class InvalidInput
{
    public static function json(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::InvalidJson, sprintf('Invalid JSON: %s', $message));
    }

    /** @param 'array'|'object' $expectedType */
    public static function rootValue(mixed $value, string $expectedType = 'object'): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnexpectedRootValue, sprintf(
            'Expected the JSON root to be an %s, got %s.',
            $expectedType,
            get_debug_type($value),
        ));
    }
}
