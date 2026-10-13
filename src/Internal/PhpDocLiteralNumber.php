<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function is_finite;
use function is_int;
use function is_numeric;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function strspn;

/**
 * Reads number literals. Only plain decimal numbers are supported, because PHP and PHPStan read others differently:
 * PHPStan casts `0x10` and `052` to 0 and 52, and clamps integers beyond PHP's range.
 *
 * @internal
 */
final class PhpDocLiteralNumber
{
    private const string INTEGER = '/\A[+-]?(?:0|[1-9][0-9]*+)\z/';
    private const string FLOAT = '/\A[+-]?(?:(?:[0-9]++\.[0-9]*+|\.[0-9]++)(?:[Ee][+-]?[0-9]++)?|[0-9]++[Ee][+-]?[0-9]++)\z/';

    /** Whether a PHPDoc name is a number literal, which starts with a digit, a sign, or a decimal point. */
    public static function isNumber(string $name): bool
    {
        return strspn($name, characters: '0123456789+-.', offset: 0, length: 1) === 1;
    }

    /** Returns the value of a plain decimal number without digit separators, or null for any other literal. */
    public static function parse(string $number): int|float|null
    {
        $float = preg_match(self::FLOAT, $number) === 1;
        if ($float && is_numeric($number)) {
            $value = (float) $number;
            return is_finite($value) ? $value : null;
        }
        $integer = preg_match(self::INTEGER, $number) === 1;
        $value = (int) $number;
        // The cast clamps integers beyond PHP's range, whose digits then differ.
        return $integer && ltrim((string) $value, characters: '-') === ltrim($number, characters: '+-') ? $value : null;
    }

    /** Whether a range bound such as the `0` in `int<0, max>` is a plain decimal integer without a plus sign. */
    public static function isRangeBound(string $name): bool
    {
        return !str_starts_with($name, '+') && is_int(self::parse($name));
    }
}
