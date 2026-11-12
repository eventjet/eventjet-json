<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function checkdate;
use function preg_match;
use function sscanf;
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
        /** @var array{int, int, int} $calendar */
        $calendar = sscanf($value, '%4d-%2d-%2d');
        [$year, $month, $day] = $calendar;
        // checkdate() rejects the year 0, which RFC 3339 allows. Leap years repeat every 400 years.
        return checkdate($month, $day, $year + 400);
    }

    public static function time(string $value): bool
    {
        $m = [];
        $matches = preg_match('/^\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|([+-])(\d{2}):(\d{2}))$/D', $value, $m) === 1;
        if (!$matches) {
            return false;
        }
        /** @var array{int, int, int} $clock */
        $clock = sscanf($value, '%2d:%2d:%2d');
        [$hour, $minute, $second] = $clock;
        if ($hour > 23 || $minute > 59 || $second > 60) {
            return false;
        }
        $offset = self::offset($m[1] ?? '', $m[2] ?? '', $m[3] ?? '');
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

    /** Returns the offset in minutes of a time, or null if it is out of range. The sign is empty for UTC. */
    private static function offset(string $sign, string $hours, string $minutes): int|null
    {
        $h = (int) $hours;
        $m = (int) $minutes;
        if ($h > 23 || $m > 59) {
            return null;
        }
        return ($sign === '+' ? 1 : -1) * (($h * 60) + $m);
    }
}
