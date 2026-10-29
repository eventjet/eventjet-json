<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeErrorKind;
use Throwable;

use function count;
use function get_debug_type;
use function implode;
use function sort;
use function sprintf;
use function var_export;

/**
 * Errors for JSON values that do not match their declared type.
 *
 * @internal
 */
final class InvalidValue
{
    public static function typeMismatch(string $path, string $expectedType, mixed $value): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s must be of type %s, %s given.',
            $path,
            $expectedType,
            get_debug_type($value),
        ));
    }

    public static function emptyList(string $path, string $type): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s must be of type %s, empty list given.',
            $path,
            $type,
        ));
    }

    public static function emptyMap(string $path): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            $path,
        ));
    }

    public static function integerKey(string $path, int $key): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s has numeric-looking member name %d, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            $path,
            $key,
        ));
    }

    public static function tupleLength(string $path, string $type, int $required, int $max, int $count): DecodeFailure
    {
        $length = $required === $max ? sprintf('exactly %d', $max) : sprintf('between %d and %d', $required, $max);
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s must be of type %s with %s items, %d given.',
            $path,
            $type,
            $length,
            $count,
        ));
    }

    /** @param 'int'|'string' $backingType */
    public static function enumBackingType(string $path, string $enum, string $backingType, mixed $value): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s uses backed enum %s, which expects a %s backing value; %s given.',
            $path,
            $enum,
            $backingType,
            get_debug_type($value),
        ));
    }

    /** @param non-empty-list<string> $enums */
    public static function unknownEnumCase(string $path, array $enums, int|string $value): DecodeFailure
    {
        sort($enums);
        return new DecodeFailure(DecodeErrorKind::InvalidValue, sprintf(
            'Field %s uses %s %s, which has no case with backing value %s.',
            $path,
            count($enums) === 1 ? 'backed enum' : 'backed enum union',
            implode('|', $enums),
            var_export($value, return: true),
        ));
    }

    /** PHP rejected the constructor call because a required member is missing from the JSON object. */
    public static function missingArgument(Throwable $error): DecodeFailure
    {
        return DecodeFailure::thrown(DecodeErrorKind::InvalidValue, $error);
    }
}
