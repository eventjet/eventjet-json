<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeErrorKind;

use function sprintf;

/**
 * Errors for classes and enums that a field declares but JSON cannot restore.
 *
 * @internal
 */
final class UnsupportedFieldClass
{
    private const string UNINSTANTIABLE = 'which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';

    public static function interface(string $field, string $interface): DecodeFailure
    {
        return self::error(sprintf('Field %s uses interface %s, %s', $field, $interface, self::UNINSTANTIABLE));
    }

    public static function abstractClass(string $field, string $class): DecodeFailure
    {
        return self::error(sprintf('Field %s uses abstract class %s, %s', $field, $class, self::UNINSTANTIABLE));
    }

    public static function unknown(string $field, string $class): DecodeFailure
    {
        return self::error(sprintf('Field %s uses unknown class %s, %s', $field, $class, self::UNINSTANTIABLE));
    }

    public static function nonFinal(string $field, string $class): DecodeFailure
    {
        return self::error(sprintf(
            'Field %s uses non-final class %s. Values may be subclasses, whose runtime class JSON does not identify.',
            $field,
            $class,
        ));
    }

    public static function nonBackedEnum(string $field, string $enum): DecodeFailure
    {
        return self::error(sprintf(
            'Field %s uses non-backed enum %s, which cannot be decoded because it has no backing value.',
            $field,
            $enum,
        ));
    }

    public static function jsonSerializableEnum(string $field, string $enum): DecodeFailure
    {
        return self::error(sprintf(
            'Field %s uses enum %s, which implements JsonSerializable. Its custom JSON representation may not match its cases, so it cannot satisfy the round-trip contract.',
            $field,
            $enum,
        ));
    }

    private static function error(string $message): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
