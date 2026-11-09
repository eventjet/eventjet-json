<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function str_contains;

/** @internal */
final class Quantifier
{
    private const string OPERATORS = '*+?{';

    /** Reads a quantifier and returns it, an empty string if there is none, or null if it is invalid. */
    public static function read(Cursor $cursor): string|null
    {
        $char = $cursor->peek();
        if ($char === null || !str_contains(self::OPERATORS, $char)) {
            return '';
        }
        $quantifier = $char === '{' ? self::braces($cursor) : $cursor->take(length: 1);
        if ($quantifier === null) {
            return null;
        }
        $lazy = $cursor->peek() === '?';
        if ($lazy) {
            $cursor->pos++;
        }
        return $lazy ? $quantifier . '?' : $quantifier;
    }

    private static function braces(Cursor $cursor): string|null
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
        return '{' . $min . ',' . $max . '}';
    }
}
