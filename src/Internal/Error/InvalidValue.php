<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
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
    public static function typeMismatch(string $expectedType, mixed $value): DecodeError
    {
        return DecodeError::invalidValue(sprintf(
            'must be of type %s, %s given.',
            $expectedType,
            get_debug_type($value),
        ));
    }

    public static function jsonValue(Throwable $error): DecodeError
    {
        return DecodeError::invalidValue(sprintf('is not a valid JSON value. %s', $error->getMessage()));
    }

    public static function emptyList(string $type): DecodeError
    {
        return DecodeError::invalidValue(sprintf('must be of type %s, empty list given.', $type));
    }

    public static function emptyMap(): DecodeError
    {
        return DecodeError::invalidValue(
            'uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
        );
    }

    public static function integerKey(int $key): DecodeError
    {
        return DecodeError::invalidValue(sprintf(
            'has numeric-looking member name %d, which PHP converts to an integer array key. Only ArrayObject<array-key, TValue> fields accept such member names.',
            $key,
        ));
    }

    public static function tupleLength(string $type, int $required, int $max, int $count): DecodeError
    {
        $length = $required === $max ? sprintf('exactly %d', $max) : sprintf('between %d and %d', $required, $max);
        return DecodeError::invalidValue(sprintf(
            'must be of type %s with %s items, %d given.',
            $type,
            $length,
            $count,
        ));
    }

    /** @param 'int'|'string' $backingType */
    public static function enumBackingType(string $enum, string $backingType, mixed $value): DecodeError
    {
        return DecodeError::invalidValue(sprintf(
            'uses backed enum %s, which expects a %s backing value; %s given.',
            $enum,
            $backingType,
            get_debug_type($value),
        ));
    }

    /** @param non-empty-list<string> $enums */
    public static function unknownEnumCase(array $enums, int|string $value): DecodeError
    {
        sort($enums);
        return DecodeError::invalidValue(sprintf(
            'uses %s %s, which has no case with backing value %s.',
            count($enums) === 1 ? 'backed enum' : 'backed enum union',
            implode('|', $enums),
            var_export($value, return: true),
        ));
    }

    public static function unknownRootEnumCase(string $enum, int|string $value): DecodeError
    {
        return new DecodeError(DecodeErrorKind::InvalidValue, sprintf(
            'Backed enum %s has no case with backing value %s.',
            $enum,
            var_export($value, return: true),
        ));
    }

    /** PHP rejected the constructor call because a required member is missing from the JSON object. */
    public static function missingArgument(Throwable $error): DecodeError
    {
        return DecodeErrors::thrown(DecodeErrorKind::InvalidValue, $error);
    }
}
