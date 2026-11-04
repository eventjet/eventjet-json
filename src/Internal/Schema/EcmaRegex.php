<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Internal\Schema\Regex\Cursor;
use Eventjet\Json\Internal\Schema\Regex\Groups;
use Eventjet\Json\Internal\Schema\Regex\Pattern;

use function preg_split;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Translates an ECMA-262 regex, read with the `u` flag as JSON Schema says, into a PCRE pattern that matches the same
 * strings.
 *
 * @internal
 */
final class EcmaRegex
{
    /**
     * Returns the PCRE pattern, with delimiters and flags, or null if the regex isn't valid ECMA-262.
     *
     * @return non-empty-string|null
     */
    public static function toPcre(string $regex): string|null
    {
        $chars = $regex === '' ? [] : preg_split('//u', $regex, limit: -1, flags: PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return null;
        }
        $cursor = new Cursor($chars);
        $groups = new Groups();
        $pcre = new Pattern($cursor, $groups)->disjunction();
        if ($pcre === null || !$cursor->atEnd() || !$groups->referencesResolve()) {
            return null;
        }
        return '/' . $pcre . '/uD';
    }
}
