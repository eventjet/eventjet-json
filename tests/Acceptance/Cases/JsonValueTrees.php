<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonValueFields;
use JsonException;
use stdClass;

use function array_map;
use function get_object_vars;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Turns JsonValue objects, and what json_decode() reads, into trees that `===` compares by PHP type and JSON shape.
 *
 * @internal
 */
final class JsonValueTrees
{
    /** @return array<string, mixed> */
    public static function fields(JsonValueFields $fields): array
    {
        return [
            'value' => self::tree($fields->value),
            'items' => array_map(self::tree(...), $fields->items),
            'members' => array_map(self::tree(...), $fields->members->getArrayCopy()),
            'nonEmpty' => array_map(self::tree(...), $fields->nonEmpty),
            'tuple' => array_map(self::tree(...), $fields->tuple),
        ];
    }

    /**
     * Returns the tree of what json_decode() reads from json_encode() of a value.
     *
     * @throws JsonException
     */
    public static function reference(mixed $value): mixed
    {
        return self::decoded(json_decode(json_encode($value, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR));
    }

    private static function tree(JsonValue $value): mixed
    {
        $inner = $value->value();
        return match (true) {
            $inner instanceof ArrayObject => ['object' => array_map(self::tree(...), $inner->getArrayCopy())],
            is_array($inner) => ['list' => array_map(self::tree(...), $inner)],
            default => $inner,
        };
    }

    private static function decoded(mixed $decoded): mixed
    {
        return match (true) {
            $decoded instanceof stdClass => ['object' => array_map(self::decoded(...), get_object_vars($decoded))],
            is_array($decoded) => ['list' => array_map(self::decoded(...), $decoded)],
            default => $decoded,
        };
    }
}
