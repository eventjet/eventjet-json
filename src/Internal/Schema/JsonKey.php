<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

use function array_map;
use function fmod;
use function is_array;
use function is_float;
use function ksort;
use function serialize;

use const SORT_STRING;

/**
 * Turns a JSON value into a string that is the same for two values exactly when JSON Schema counts them as equal:
 * numbers by their mathematical value, arrays by their items in order, and objects by their members in any order.
 *
 * @internal
 */
final class JsonKey
{
    private const float INT_LIMIT = 9.223_372_036_854_775_807E18;

    /** @mutation-free */
    public static function isIntegral(float $value): bool
    {
        return fmod($value, num2: 1.0) === 0.0;
    }

    /**
     * Whether a float lies inside the range of PHP integers.
     *
     * @mutation-free
     */
    public static function fitsInt(float $value): bool
    {
        return $value >= -self::INT_LIMIT && $value < self::INT_LIMIT;
    }

    public static function of(mixed $value): string
    {
        return serialize(self::canonical($value));
    }

    /** Turns integral floats into integers and sorts the members of objects, recursively. */
    private static function canonical(mixed $value): mixed
    {
        return match (true) {
            is_float($value) => self::canonicalNumber($value),
            is_array($value) => array_map(self::canonical(...), $value),
            $value instanceof stdClass => self::canonicalObject($value),
            default => $value,
        };
    }

    private static function canonicalNumber(float $value): int|float
    {
        return self::isIntegral($value) && self::fitsInt($value) ? (int) $value : $value;
    }

    private static function canonicalObject(stdClass $object): object
    {
        $members = array_map(self::canonical(...), (array) $object);
        ksort($members, SORT_STRING);
        return (object) $members;
    }
}
