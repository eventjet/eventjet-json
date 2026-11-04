<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

use function floor;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function ksort;
use function strlen;
use function var_export;

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

    public static function of(mixed $value): string
    {
        if ($value === null) {
            return 'n';
        }
        if (is_bool($value)) {
            return $value ? 't' : 'f';
        }
        if (is_int($value)) {
            return 'i' . $value;
        }
        if (is_float($value)) {
            if (
                is_finite($value)
                && floor($value) === $value
                && $value > -self::INT_LIMIT
                && $value < self::INT_LIMIT
            ) {
                return 'i' . (int) $value;
            }
            return 'd' . var_export($value, return: true);
        }
        if (is_string($value)) {
            return 's' . strlen($value) . ':' . $value;
        }
        if (is_array($value)) {
            $key = 'a[';
            /** @var mixed $item */
            foreach ($value as $item) {
                $itemKey = self::of($item);
                $key .= strlen($itemKey) . ':' . $itemKey;
            }
            return $key . ']';
        }
        $members = [];
        if ($value instanceof stdClass) {
            /** @var mixed $member */
            foreach ((array) $value as $name => $member) {
                $members[$name] = self::of($member);
            }
        }
        ksort($members, SORT_STRING);
        $key = 'o{';
        foreach ($members as $name => $memberKey) {
            $key .= strlen((string) $name) . ':' . $name . strlen($memberKey) . ':' . $memberKey;
        }
        return $key . '}';
    }
}
