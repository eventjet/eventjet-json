<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeErrorKind;
use ReflectionClass;
use Throwable;

use function sprintf;

/**
 * Errors for target classes that cannot be decoded as a whole.
 *
 * @internal
 */
final class UnsupportedTarget
{
    public static function interface(): DecodeFailure
    {
        return self::error(
            'Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
        );
    }

    public static function abstractClass(): DecodeFailure
    {
        return self::error(
            'Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
        );
    }

    public static function inaccessibleConstructor(string $visibility): DecodeFailure
    {
        return self::error(sprintf(
            'Target class has a %s constructor, which cannot be called to create the object.',
            $visibility,
        ));
    }

    public static function nonBackedEnum(string $enum): DecodeFailure
    {
        return self::error(sprintf(
            'Target type non-backed enum %s, which cannot be decoded because it has no backing value.',
            $enum,
        ));
    }

    public static function backedEnum(): DecodeFailure
    {
        return self::error(
            'Backed enums are not supported as standalone targets. Use a typed field or a collection descriptor.',
        );
    }

    public static function nonFinalItem(): DecodeFailure
    {
        return self::error(
            'Target type is a non-final class. Collection items may be subclasses, whose runtime class JSON does not identify.',
        );
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    public static function jsonSerializable(ReflectionClass $class): DecodeFailure
    {
        $enum = $class->isEnum();
        return self::error(sprintf(
            'Target type %s JsonSerializable. Its custom JSON representation may not match its %s, so it cannot satisfy the round-trip contract.',
            $enum ? 'is an enum that implements' : 'implements',
            $enum ? 'cases' : 'constructor parameters',
        ));
    }

    /** Reading the declarations threw, for example because a class does not exist or an attribute is invalid. */
    public static function unreadable(Throwable $error): DecodeFailure
    {
        return DecodeFailure::thrown(DecodeErrorKind::UnsupportedDeclaration, $error);
    }

    /**
     * PHP refused to assign a public property without a set hook: it is readonly and the constructor already set it,
     * or it is virtual. Either way, the class cannot accept the member that json_encode() writes for the property.
     */
    public static function unassignableProperty(Throwable $error): DecodeFailure
    {
        return DecodeFailure::thrown(DecodeErrorKind::UnsupportedDeclaration, $error);
    }

    private static function error(string $message): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
