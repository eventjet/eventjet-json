<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function get_debug_type;
use function sprintf;

/**
 * Errors for documents whose root value does not have the type that the target needs, or that are not JSON.
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

    /** @param 'int'|'string' $backingType */
    public static function enumRootValue(string $enum, string $backingType, mixed $value): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnexpectedRootValue, sprintf(
            'Expected the JSON root to be %s backing value of backed enum %s, got %s.',
            $backingType === 'int' ? 'an int' : 'a string',
            $enum,
            get_debug_type($value),
        ));
    }
}
