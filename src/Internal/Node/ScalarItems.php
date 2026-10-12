<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Checks the items of scalar lists and maps. Each type has its own loop, because a call or dispatch per item makes
 * long collections much slower.
 *
 * @internal
 */
final class ScalarItems
{
    /**
     * Returns the key of the first item that is not of the given type: bool, int, string, or float.
     *
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @return TKey|null
     */
    public static function firstInvalid(array $values, string $type): int|string|null
    {
        return match ($type) {
            'bool' => self::firstNonBool($values),
            'int' => self::firstNonInt($values),
            'string' => self::firstNonString($values),
            default => self::firstNonNumber($values),
        };
    }

    /**
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @return TKey|null
     */
    private static function firstNonBool(array $values): int|string|null
    {
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (!is_bool($value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @return TKey|null
     */
    private static function firstNonInt(array $values): int|string|null
    {
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (!is_int($value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @return TKey|null
     */
    private static function firstNonString(array $values): int|string|null
    {
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (!is_string($value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * @template TKey of array-key
     * @param array<TKey, mixed> $values
     * @return TKey|null
     */
    private static function firstNonNumber(array $values): int|string|null
    {
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (!is_float($value) && !is_int($value)) {
                return $key;
            }
        }
        return null;
    }
}
