<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function checkdate;
use function preg_match;
use function strcspn;
use function substr;

/**
 * Checks for the date and time formats of RFC 3339 and ISO 8601 durations.
 *
 * @internal
 */
final class Dates
{
    public static function date(string $value): bool
    {
        $matches = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1;
        if (!$matches) {
            return false;
        }
        $year = (int) substr($value, offset: 0, length: 4);
        $month = (int) substr($value, offset: 5, length: 2);
        $day = (int) substr($value, offset: 8, length: 2);
        // checkdate() rejects the year 0, which RFC 3339 allows. Leap years repeat every 400 years.
        return checkdate($month, $day, $year + 400);
    }

    public static function time(string $value): bool
    {
        $matches = preg_match('/^\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|[+-]\d{2}:\d{2})$/D', $value) === 1;
        if (!$matches) {
            return false;
        }
        $hour = (int) substr($value, offset: 0, length: 2);
        $minute = (int) substr($value, offset: 3, length: 2);
        $second = (int) substr($value, offset: 6, length: 2);
        if ($hour > 23 || $minute > 59 || $second > 60) {
            return false;
        }
        $offset = self::offset($value);
        if ($offset === null) {
            return false;
        }
        if ($second !== 60) {
            return true;
        }
        $utc = (($hour * 60) + $minute - $offset + 1440) % 1440;
        return $utc === ((23 * 60) + 59);
    }

    public static function dateTime(string $value): bool
    {
        $separator = strcspn($value, characters: 'Tt');
        return (
            self::date(substr($value, offset: 0, length: $separator))
            && self::time(substr($value, offset: $separator + 1))
        );
    }

    public static function duration(string $value): bool
    {
        $time = 'T(?:\d+H(?:\d+M(?:\d+S)?)?|\d+M(?:\d+S)?|\d+S)';
        $date = '(?:\d+D|\d+M(?:\d+D)?|\d+Y(?:\d+M(?:\d+D)?)?)';
        return preg_match('/^P(?:' . $date . '(?:' . $time . ')?|' . $time . '|\d+W)$/D', $value) === 1;
    }

    /** Returns the offset in minutes of a time that matches the time pattern, or null if it is out of range. */
    private static function offset(string $time): int|null
    {
        $sign = substr($time, offset: -6, length: 1);
        if ($sign !== '+' && $sign !== '-') {
            return 0;
        }
        $hours = (int) substr($time, offset: -5, length: 2);
        $minutes = (int) substr($time, offset: -2);
        if ($hours > 23 || $minutes > 59) {
            return null;
        }
        return ($sign === '+' ? 1 : -1) * (($hours * 60) + $minutes);
    }
}
