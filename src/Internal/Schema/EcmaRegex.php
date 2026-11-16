<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Internal\Schema\Regex\Cursor;
use Eventjet\Json\Internal\Schema\Regex\Groups;
use Eventjet\Json\Internal\Schema\Regex\Pattern;

use function assert;
use function preg_match;
use function preg_split;
use function restore_error_handler;
use function set_error_handler;

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
     * Returns the PCRE pattern, with delimiters and flags, null if the regex isn't valid ECMA-262, or false if PCRE
     * can't run it or can't match it the same way.
     *
     * @return non-empty-string|false|null
     */
    public static function toPcre(string $regex): string|false|null
    {
        $chars = $regex === '' ? [] : preg_split('//u', $regex, limit: -1, flags: PREG_SPLIT_NO_EMPTY);
        assert($chars !== false, description: 'Schemas and instances are valid UTF-8.');
        $cursor = new Cursor($chars);
        $groups = new Groups();
        $pcre = new Pattern($cursor, $groups)->disjunction();
        $valid = $pcre !== null && $cursor->atEnd() && $groups->resolveReferences();
        if (!$valid) {
            return null;
        }
        if ($groups->referencesRepeatedGroup()) {
            return false;
        }
        $pcre = '/' . $pcre . '/uD';
        $runs = self::runs($pcre);
        return $runs ? $pcre : false;
    }

    /** @param non-empty-string $pcre */
    private static function runs(string $pcre): bool
    {
        set_error_handler(static fn(): bool => true);
        $runs = preg_match($pcre, subject: '') !== false;
        restore_error_handler();
        return $runs;
    }
}
