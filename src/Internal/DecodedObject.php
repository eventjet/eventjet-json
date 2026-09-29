<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use WeakMap;

/**
 * @internal
 */
final readonly class DecodedObject
{
    /**
     * @param array<array-key, JsonNode> $members
     * @param array<array-key, JsonNode> $unknown
     * @param array<array-key, ValueSnapshot|null> $omittedDefaults
     */
    public function __construct(public array $members, public array $unknown, public array $omittedDefaults)
    {
    }

    /**
     * @return WeakMap<object, self>
     */
    public static function registry(): WeakMap
    {
        /** @var WeakMap<object, self>|null $registry */
        static $registry = null;
        if ($registry === null) {
            /** @var WeakMap<object, self> $empty */
            $empty = new WeakMap();
            $registry = $empty;
        }
        return $registry;
    }
}
