<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use JsonException;
use stdClass;

use function array_all;
use function array_key_exists;
use function count;
use function get_object_vars;
use function is_array;
use function is_float;
use function is_int;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Equality of JSON values as JSON Schema defines it, written apart from the validator to check it against.
 *
 * @internal
 */
final class JsonEquality
{
    /** @throws JsonException */
    public static function equal(mixed $a, mixed $b): bool
    {
        return self::same(self::decoded($a), self::decoded($b));
    }

    /** @throws JsonException */
    private static function decoded(mixed $value): mixed
    {
        return json_decode(
            json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private static function same(mixed $a, mixed $b): bool
    {
        return match (true) {
            self::isNumber($a) && self::isNumber($b) => self::sameNumber($a, $b),
            $a instanceof stdClass && $b instanceof stdClass => self::sameMembers(
                get_object_vars($a),
                get_object_vars($b),
            ),
            is_array($a) && is_array($b) => self::sameMembers($a, $b),
            default => $a === $b,
        };
    }

    /**
     * @pure
     * @psalm-assert-if-true int|float $value
     */
    private static function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private static function sameNumber(int|float $a, int|float $b): bool
    {
        return is_int($a) && is_int($b) ? $a === $b : (float) $a === (float) $b;
    }

    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     */
    private static function sameMembers(array $a, array $b): bool
    {
        return (
            count($a) === count($b)
            && array_all(
                $a,
                static fn(mixed $member, int|string $key): bool => (
                    array_key_exists($key, $b) && self::same($member, $b[$key] ?? null)
                ),
            )
        );
    }
}
