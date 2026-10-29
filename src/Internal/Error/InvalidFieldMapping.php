<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeErrorKind;

use function sprintf;

/**
 * Errors for `#[Field]` annotations that cannot rename a field.
 *
 * @internal
 */
final class InvalidFieldMapping
{
    public static function withoutJsonSerializable(): DecodeFailure
    {
        return self::error('#[Field] requires the target class to implement JsonSerializable.');
    }

    public static function nonPublicProperty(string $property): DecodeFailure
    {
        return self::error(sprintf('#[Field] requires a public instance property; %s is not one.', $property));
    }

    public static function repeated(string $property): DecodeFailure
    {
        return self::error(sprintf('Property %s must not repeat #[Field].', $property));
    }

    public static function collision(string $first, string $second, string $name): DecodeFailure
    {
        return self::error(sprintf('Properties %s and %s use the same JSON field name "%s".', $first, $second, $name));
    }

    private static function error(string $message): DecodeFailure
    {
        return new DecodeFailure(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
