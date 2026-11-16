<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function str_contains;

/** @internal */
final class Quantifier
{
    private const string OPERATORS = '*+?{';

    /**
     * Reads a quantifier and returns it, an empty string if there is none, or null if it is invalid. If it lets the
     * atom match more than once, it marks the groups of the atom, those after the given number, as repeated.
     */
    public static function read(Cursor $cursor, Groups $groups, int $groupsBefore): string|null
    {
        $char = $cursor->peek();
        if ($char === null || !str_contains(self::OPERATORS, $char)) {
            return '';
        }
        $quantifier = $char === '{' ? self::braces($cursor) : [$cursor->take(length: 1), $char !== '?'];
        if ($quantifier === null) {
            return null;
        }
        if ($quantifier[1]) {
            $groups->repeat(after: $groupsBefore);
        }
        // Whether a quantifier is lazy changes which text matches, but never whether the regex matches.
        if ($cursor->peek() === '?') {
            $cursor->pos++;
        }
        return $quantifier[0];
    }

    /** @return array{string, bool}|null The quantifier as PCRE and whether its maximum is above 1. */
    private static function braces(Cursor $cursor): array|null
    {
        $cursor->pos++;
        $min = $cursor->span(Cursor::DECIMAL);
        if ($min === '') {
            return null;
        }
        $max = $min;
        if ($cursor->peek() === ',') {
            $cursor->pos++;
            $max = $cursor->span(Cursor::DECIMAL);
        }
        if ($cursor->peek() !== '}' || $max !== '' && $max < $min) {
            return null;
        }
        $cursor->pos++;
        return ['{' . $min . ',' . $max . '}', $max === '' || $max > 1];
    }
}
