<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function sprintf;

/** @internal */
final class MapDecodeError
{
    /** @param class-string $class */
    public static function empty(string $class, string $field): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            $field,
        ));
    }

    /** @param class-string $class */
    public static function ambiguousDeclaration(string $class, string $field): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
            $field,
        ));
    }

    /** @param class-string $class */
    public static function unsupportedDeclaration(string $class, string $field, string $type): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s uses unsupported map declaration %s. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            $field,
            $type,
        ));
    }

    /** @param class-string $class */
    public static function numericKey(string $class, string $field, int $key): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s has numeric-looking member name %d, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            $field,
            $key,
        ));
    }
}
