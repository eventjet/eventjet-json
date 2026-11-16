<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use stdClass;

use function abs;
use function array_map;
use function array_reverse;
use function floor;
use function get_object_vars;
use function is_array;
use function is_float;
use function is_int;

/**
 * Values that are equal to a given decoded JSON value under JSON Schema equality, but not identical to it.
 *
 * @internal
 */
final class EqualVariants
{
    /** Reverses the order of object members and turns integers into floats and back. */
    public static function of(mixed $value): mixed
    {
        return match (true) {
            $value instanceof stdClass => self::object($value),
            is_array($value) => array_map(self::of(...), $value),
            is_int($value), is_float($value) => self::number($value),
            default => $value,
        };
    }

    private static function object(stdClass $object): object
    {
        return (object) array_map(self::of(...), array_reverse(get_object_vars($object), preserve_keys: true));
    }

    private static function number(int|float $number): int|float
    {
        if (abs($number) >= (2 ** 53) || (float) $number !== floor($number)) {
            return $number;
        }
        return is_int($number) ? (float) $number : (int) $number;
    }
}
