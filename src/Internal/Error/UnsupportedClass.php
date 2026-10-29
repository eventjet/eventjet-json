<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;

/**
 * Errors for a class or an enum that cannot be decoded where a declaration names it. In a field, they name the field.
 * At the root and in a JsonType, the caller names the class.
 *
 * @internal
 */
final class UnsupportedClass
{
    public static function interface(string|null $field, string $interface): DecodeError
    {
        return $field === null ? UnsupportedTarget::interface() : UnsupportedFieldClass::interface($field, $interface);
    }

    public static function abstractClass(string|null $field, string $class): DecodeError
    {
        return $field === null
            ? UnsupportedTarget::abstractClass()
            : UnsupportedFieldClass::abstractClass($field, $class);
    }

    public static function nonFinal(string|null $field, string $class): DecodeError
    {
        return $field === null ? UnsupportedTarget::nonFinalItem() : UnsupportedFieldClass::nonFinal($field, $class);
    }

    public static function nonBackedEnum(string|null $field, string $enum): DecodeError
    {
        return $field === null
            ? UnsupportedTarget::nonBackedEnum($enum)
            : UnsupportedFieldClass::nonBackedEnum($field, $enum);
    }

    public static function jsonSerializableEnum(string|null $field, string $enum): DecodeError
    {
        return $field === null
            ? UnsupportedTarget::jsonSerializable($enum)
            : UnsupportedFieldClass::jsonSerializableEnum($field, $enum);
    }
}
