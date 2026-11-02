<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function sprintf;

/**
 * Errors for `array` and `ArrayObject` fields whose PHPDoc declares no supported collection.
 *
 * @internal
 */
final class UnsupportedCollection
{
    /** @param 'param'|'var' $tag */
    public static function unrecognized(string $field, string $tag): DecodeError
    {
        return self::error(sprintf(
            'Field %1$s has a missing or unrecognized collection declaration. Use @%2$s, @phpstan-%2$s, @psalm-%2$s, or @phan-%2$s with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            $field,
            $tag,
        ));
    }

    public static function genericArrayMap(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
            $field,
        ));
    }

    public static function map(string $field, string $type): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses unsupported map declaration %s. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            $field,
            $type,
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
