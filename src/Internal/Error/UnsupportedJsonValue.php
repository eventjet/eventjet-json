<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonValue;

use function sprintf;

/**
 * Errors for JsonValue declarations that cannot round-trip.
 *
 * @internal
 */
final class UnsupportedJsonValue
{
    public static function union(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses %s in a union. It takes every JSON value, null included, so JSON cannot identify another member.',
            $field,
            JsonValue::class,
        ));
    }

    public static function withoutSerializer(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %1$s uses %2$s|null, which requires the class to implement JsonSerializable. json_encode() writes a null property as JSON null, which decodes as a %2$s that holds null, so the serializer must leave out the member instead.',
            $field,
            JsonValue::class,
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
