<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function count;
use function is_float;
use function is_int;
use function str_starts_with;

/**
 * Integer ranges such as `int<0, max>`. A bound is `min` or `max` at its end, or a decimal integer without a plus sign.
 * Any other number as a bound makes the range unsupported.
 *
 * @internal
 */
final class PhpDocIntegerRange
{
    /** Whether a type is an integer range. */
    public static function matches(PhpDocType $type): bool
    {
        if ($type->name !== 'int' || count($type->arguments) !== 2) {
            return false;
        }
        foreach ($type->arguments as $index => $bound) {
            $valid = $bound->isPlainName($index === 0 ? 'min' : 'max') || self::isNumberBound($bound->literal);
            if (!$valid) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the first number in the arguments of `int<…, …>` that PHP and PHPStan can read differently, or that is no
     * valid bound.
     */
    public static function unsupportedNumber(PhpDocType $range): string|null
    {
        foreach ($range->arguments as $bound) {
            $value = $bound->literal?->value;
            $invalid = (is_int($value) || is_float($value)) && !self::isNumberBound($bound->literal);
            $number = $invalid ? $bound->name : $bound->unsupportedNumber;
            if ($number !== null) {
                return $number;
            }
        }
        return null;
    }

    private static function isNumberBound(PhpDocLiteral|null $literal): bool
    {
        return $literal !== null && is_int($literal->value) && !str_starts_with($literal->name, '+');
    }
}
