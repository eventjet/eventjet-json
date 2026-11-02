<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_map;
use function sort;

/**
 * The member names that a map accepts, decided by the key type of its declaration.
 *
 * @internal
 */
enum MapKeys
{
    /** `string`: names that PHP keeps as string keys. */
    case String;

    /** `array-key`, `int|string`, or `string|int`: every name. */
    case Any;

    /** Classifies a key type as written or with resolved names, or returns null for an unsupported key type. */
    public static function of(PhpDocType $key): self|null
    {
        $names = array_map(static fn(PhpDocType $member): string|null => $member->plainName(), $key->members());
        sort($names);
        return match ($names) {
            ['string'] => self::String,
            ['array-key'], ['int', 'string'] => self::Any,
            default => null,
        };
    }
}
