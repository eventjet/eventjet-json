<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;

/**
 * Builds parsed types. A type is null where the decoder does not support its syntax, and so is any type containing it.
 *
 * @internal
 */
final class PhpDocTypeTree
{
    /**
     * Returns a union, intersection, or generic type such as `list<int>`.
     *
     * @param list<PhpDocType|null> $arguments
     */
    public static function named(string $name, array $arguments): PhpDocType|null
    {
        if (in_array(null, $arguments, strict: true)) {
            return null;
        }
        /** @var list<PhpDocType> $arguments */
        return new PhpDocType($name, $arguments);
    }

    /** @param list<PhpDocTupleEntry|null> $entries */
    public static function shape(string $kind, array $entries): PhpDocType|null
    {
        if (in_array(null, $entries, strict: true)) {
            return null;
        }
        /** @var list<PhpDocTupleEntry> $entries */
        return new PhpDocType($kind . '{}', entries: $entries);
    }

    /** Returns null for types nested more than 64 levels deep. Unions and parentheses do not add a level. */
    public static function limit(PhpDocType|null $type, int $depth = 0): PhpDocType|null
    {
        if ($type === null || $depth >= 64) {
            return null;
        }
        $next = $type->name === '|' || $type->name === '&' ? $depth : $depth + 1;
        foreach ($type->arguments as $argument) {
            $fits = self::limit($argument, $next);
            if ($fits === null) {
                return null;
            }
        }
        foreach ($type->entries as $entry) {
            $fits = self::limit($entry->type, $depth + 1);
            if ($fits === null) {
                return null;
            }
        }
        return $type;
    }
}
