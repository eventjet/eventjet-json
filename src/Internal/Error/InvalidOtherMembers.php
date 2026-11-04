<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonValue;

use function sprintf;

/**
 * Errors for `#[OtherMembers]` declarations that cannot collect the unknown members of a JSON object.
 *
 * @internal
 */
final class InvalidOtherMembers
{
    public static function withoutJsonSerializable(): DecodeError
    {
        return self::error(
            '#[OtherMembers] requires the target class to implement JsonSerializable, because json_encode() would write the collected members as one nested member.',
        );
    }

    public static function nonPublicProperty(string $property): DecodeError
    {
        return self::error(sprintf('#[OtherMembers] requires a public instance property; %s is not one.', $property));
    }

    public static function repeated(string $first, string $second): DecodeError
    {
        return self::error(sprintf(
            'Properties %s and %s both declare #[OtherMembers], but only one property can collect the other members.',
            $first,
            $second,
        ));
    }

    public static function withField(string $property): DecodeError
    {
        return self::error(sprintf(
            'Property %s declares both #[Field] and #[OtherMembers]. The property that collects the other members has no JSON name of its own.',
            $property,
        ));
    }

    public static function type(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s collects the other members, so it must be declared as array<array-key, %s>.',
            $field,
            JsonValue::class,
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
