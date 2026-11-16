<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function str_starts_with;
use function strspn;
use function substr;

/**
 * Checks for the formats of JSON Pointers and relative JSON Pointers.
 *
 * @internal
 */
final class JsonPointers
{
    public static function jsonPointer(string $value): bool
    {
        return ($value === '' || str_starts_with($value, '/')) && Units::cover($value, '[^~]++|~[01]');
    }

    public static function relativeJsonPointer(string $value): bool
    {
        $digits = strspn($value, characters: '0123456789');
        $number = substr($value, offset: 0, length: $digits);
        $rest = substr($value, $digits);
        $validNumber = $number === '0' || $number !== '' && !str_starts_with($number, '0');
        return $validNumber && ($rest === '#' || self::jsonPointer($rest));
    }
}
