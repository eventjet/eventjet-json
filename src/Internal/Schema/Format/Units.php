<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function preg_replace;

/**
 * Checks text with PCRE one unit at a time. A single match that repeats a group can hit PCRE's backtrack or JIT stack
 * limit on a long value. Here every unit is a match of its own, so no length of text can.
 *
 * @internal
 */
final class Units
{
    /**
     * Returns whether the text is a sequence of the units.
     *
     * @param string $units PCRE alternatives, with `/` escaped. Each matches a bounded unit or a possessive run of one
     *     class.
     */
    public static function cover(string $text, string $units): bool
    {
        return preg_replace('/\G(?:' . $units . ')/u', replacement: '', subject: $text) === '';
    }
}
