<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use InvalidArgumentException;

use function get_debug_type;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function var_export;

/**
 * Checks the scalars and member names that a JsonValue holds, and turns scalars into what json_decode() reads from
 * their json_encode() output.
 *
 * @internal
 */
final class CanonicalScalar
{
    /**
     * Returns what json_decode() reads from json_encode() of a scalar, null or a backed enum. That is an integer for a
     * whole float that json_encode() writes without an exponent.
     *
     * @throws InvalidArgumentException
     */
    public static function of(mixed $value): bool|float|int|string|null
    {
        if (is_string($value)) {
            self::assertUtf8($value, 'Strings');
            return $value;
        }
        if (is_float($value) && !is_finite($value)) {
            $float = var_export($value, return: true);
            throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold the float %s, which has no JSON representation.',
                $float,
            ));
        }
        /** @var bool|float|int|string|null */
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_float($value) => json_decode((string) json_encode($value)),
            $value instanceof BackedEnum => $value->value,
            default => throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold %s. It accepts null, scalars, arrays, stdClass, ArrayObject, JsonSerializable objects and backed enums.',
                get_debug_type($value),
            )),
        };
    }

    /** @throws InvalidArgumentException */
    public static function assertMemberName(int|string $name): void
    {
        if (is_int($name)) {
            return;
        }
        self::assertUtf8($name, 'Member names');
        if (str_starts_with($name, "\0")) {
            throw new InvalidArgumentException(
                'Member names in a JsonValue must not start with a null byte, because json_decode() cannot read them.',
            );
        }
    }

    /**
     * @param string $kind What the string is, for the error message.
     * @throws InvalidArgumentException
     */
    private static function assertUtf8(string $value, string $kind): void
    {
        $valid = preg_match('//u', $value) === 1;
        if (!$valid) {
            throw new InvalidArgumentException(sprintf(
                '%s in a JsonValue must be valid UTF-8, which json_encode() requires.',
                $kind,
            ));
        }
    }
}
