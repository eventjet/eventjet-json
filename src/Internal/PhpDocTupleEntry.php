<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class PhpDocTupleEntry
{
    public function __construct(
        public PhpDocType $type,
        public string|null $key = null,
        public bool $optional = false,
    ) {}

    /**
     * Returns the number of required entries, or null if the entries are not positional with optional entries last.
     *
     * @param list<self> $entries
     */
    public static function required(array $entries): int|null
    {
        $required = 0;
        foreach ($entries as $index => $entry) {
            $positional = $entry->key === null || $entry->key === (string) $index;
            if (!$positional || $required < $index && !$entry->optional) {
                return null;
            }
            $required += $entry->optional ? 0 : 1;
        }
        return $required;
    }
}
