<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function intdiv;
use function is_finite;
use function is_numeric;
use function ltrim;
use function preg_match;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

use const PHP_INT_MIN;

/** @internal */
final class PhpDocLiteralNumber
{
    public static function parse(string $source): int|float|null
    {
        $number = str_replace('_', replace: '', subject: strtolower($source));
        $float = preg_match('/\A[+-]?(?:(?:[0-9]+\.[0-9]*|\.[0-9]+)(?:e[+-]?[0-9]+)?|[0-9]+e[+-]?[0-9]+)\z/', $number);
        if ($float === 1 && is_numeric($number)) {
            $value = (float) $number;
            return is_finite($value) ? $value : null;
        }
        $integer = preg_match('/\A[+-]?(?:0x[0-9a-f]+|0b[01]+|0o[0-7]+|[0-9]+)\z/', $number);
        if ($integer !== 1) {
            return null;
        }
        $negative = $number[0] === '-';
        $digits = ltrim($number, characters: '+-');
        $base = match (substr($digits, offset: 0, length: 2)) {
            '0x' => 16,
            '0b' => 2,
            '0o' => 8,
            default => 10,
        };
        if ($base !== 10) {
            $digits = substr($digits, offset: 2);
        }
        $value = 0;
        /**
         * @mago-expect analysis:unhandled-thrown-type The divisor is positive, so division by zero is impossible.
         * @mago-expect analysis:unhandled-thrown-type The divisor cannot be -1, so PHP_INT_MIN cannot overflow.
         */
        $minimum = intdiv(PHP_INT_MIN, $base);
        // Accumulate negatively so PHP_INT_MIN remains representable.
        for ($index = 0; $index < strlen($digits); $index++) {
            $digit = strpos('0123456789abcdef', $digits[$index]);
            if ($digit === false) {
                return null;
            }
            if ($value < $minimum) {
                return null;
            }
            $value *= $base;
            if ($value < (PHP_INT_MIN + $digit)) {
                return null;
            }
            $value -= $digit;
        }
        if ($negative) {
            return $value;
        }
        return $value === PHP_INT_MIN ? null : -$value;
    }
}
