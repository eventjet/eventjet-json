<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\Internal\PhpDocTag;
use Eventjet\Json\Internal\PhpDocType;

use function array_map;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Describes types and tags as JSON, so that they compare as strings and read well in a failure message.
 *
 * @internal
 */
final class TypeDescription
{
    /** @param list<PhpDocTag> $tags */
    public static function tags(array $tags): string
    {
        $trees = [];
        foreach ($tags as $tag) {
            $trees[] = [$tag->name, $tag->variable, self::tree($tag->type)];
        }
        return self::json($trees);
    }

    public static function type(PhpDocType|null $type): string
    {
        return self::json(self::tree($type));
    }

    public static function json(mixed $value): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
        try {
            return json_encode($value, $flags);
        } catch (\JsonException) {
            return '(not describable)';
        }
    }

    /** @return list<mixed>|null */
    private static function tree(PhpDocType|null $type): array|null
    {
        if ($type === null) {
            return null;
        }
        $entries = [];
        foreach ($type->entries as $entry) {
            $entries[] = [self::tree($entry->type), $entry->key, $entry->optional];
        }
        return [$type->name, array_map(self::tree(...), $type->arguments), $entries];
    }
}
