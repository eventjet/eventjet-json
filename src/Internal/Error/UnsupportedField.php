<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function sprintf;

/**
 * Errors for field declarations that cannot round-trip.
 *
 * @internal
 */
final class UnsupportedField
{
    private const string IMPRECISE = 'The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.';

    public static function untyped(string $field): DecodeError
    {
        return self::error(sprintf('Field %s uses no type declaration. %s', $field, self::IMPRECISE));
    }

    public static function imprecise(string $field, string $type): DecodeError
    {
        return self::error(sprintf('Field %s uses unsupported type %s. %s', $field, $type, self::IMPRECISE));
    }

    public static function intersection(string $field, string $type): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses unsupported intersection type %s. JSON does not identify a concrete class to instantiate.',
            $field,
            $type,
        ));
    }

    public static function publicProperty(string $field, string $type): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses unsupported public property type %s. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            $field,
            $type,
        ));
    }

    public static function nonEncodable(string $field, string $type): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses non-JSON-encodable type %s, which has no JSON representation.',
            $field,
            $type,
        ));
    }

    public static function literal(string $field, string $literal): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses unsupported literal declaration %s, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            $field,
            $literal,
        ));
    }

    public static function number(string $field, string $literal): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses unsupported number literal %s, which PHP and PHPStan can read differently. Use a plain decimal number, such as 42, -7, or 1.5.',
            $field,
            $literal,
        ));
    }

    public static function variadic(string $parameter): DecodeError
    {
        return self::error(sprintf(
            'Constructor parameter %s is variadic. JSON members bind to individual named arguments, not variadic argument lists.',
            $parameter,
        ));
    }

    public static function withoutProperty(string $parameter): DecodeError
    {
        return self::error(sprintf(
            'Constructor parameter %s has no same-named declared public instance property. The target class does not expose a stable JSON member from which the argument can be recovered.',
            $parameter,
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
