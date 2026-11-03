<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonValue;

use function sprintf;

/**
 * Declarations of JsonValue that the decoder cannot restore.
 *
 * @internal
 */
final class UnsupportedJsonValue
{
    public static function outsideFields(): DecodeError
    {
        return self::error(sprintf(
            'Target type is %s, which is supported only in fields, list items, map values and tuple positions, not as the root type or as a JsonType item.',
            JsonValue::class,
        ));
    }

    public static function nullableWithoutSerializer(string $field): DecodeError
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
