<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ArrayObject;
use Eventjet\Json\Internal\CanonicalScalar;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use stdClass;
use Throwable;

use function array_is_list;
use function get_debug_type;
use function get_object_vars;
use function is_array;
use function max;
use function sprintf;

/**
 * Any JSON value, for fields that hold free-form JSON. It stores what a json_encode()/json_decode() round trip of its
 * input gives, so `1.0` becomes `1` and `-0.0` becomes `0`, and returns one level at a time: nested arrays and objects
 * hold JsonValue objects again.
 *
 * Nested JsonValue objects in the input keep their stored form without checking it again. Every JsonValue also stores
 * its height, the number of levels of arrays and objects in it, so that they count towards the nesting limit.
 *
 * @psalm-type Stored = bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
 * @phpstan-type Stored bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
 * @api
 */
final class JsonValue implements JsonSerializable
{
    /** The nesting limit of json_encode() and json_decode() by default. */
    private const int MAX_DEPTH = 512;

    private const string TOO_DEEP =
        'JsonValue cannot hold values nested more than '
            . self::MAX_DEPTH
            . ' levels deep. Arrays, objects and jsonSerialize() calls each count as a level.';

    /** @var Stored */
    private bool|float|int|string|array|ArrayObject|null $value;

    /** How many levels of arrays and objects the value has. */
    private int $height;

    /**
     * Accepts null, scalars, lists, associative arrays, stdClass, ArrayObject, JsonSerializable objects and backed
     * enums, nested in any combination.
     *
     * @throws InvalidArgumentException For input that isn't JSON-shaped, such as a closure, a float that isn't finite,
     *     a string that isn't valid UTF-8, a member name that starts with a null byte, or input nested more than 512
     *     levels deep. Also if a jsonSerialize() method throws, with its exception as the previous one.
     */
    public function __construct(mixed $value)
    {
        [$this->value, $this->height] = $this->canonical($value, 0);
    }

    /**
     * Returns the value. A JSON array is a list and a JSON object is an ArrayObject; changing either doesn't change
     * this value.
     *
     * @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
     */
    public function value(): bool|float|int|string|array|ArrayObject|null
    {
        return $this->value instanceof ArrayObject ? clone $this->value : $this->value;
    }

    /** @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null */
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->value();
    }

    /**
     * Returns the stored form of a value below the given number of levels, and the number of levels that it reaches:
     * what json_decode() reads from its json_encode() output, with every nested array and object wrapped in a
     * JsonValue. The levels count arrays, objects and jsonSerialize() calls, so that a jsonSerialize() method that
     * returns its own object can't recurse forever. Heights leave out the jsonSerialize() calls, because the stored form
     * has none. json_encode() prefers jsonSerialize() over the backing value of an enum, so this does too.
     *
     * @return array{Stored, int}
     * @throws InvalidArgumentException
     */
    private function canonical(mixed $value, int $depth): array
    {
        return match (true) {
            $value instanceof self => [$value->value, self::deeper($depth, $value->height)],
            is_array($value) => array_is_list($value) ? $this->items($value, $depth) : $this->members($value, $depth),
            $value instanceof JsonSerializable => $this->serialized($value, $depth),
            $value instanceof stdClass => $this->members(get_object_vars($value), $depth),
            $value instanceof ArrayObject => $this->members($value->getArrayCopy(), $depth),
            default => [CanonicalScalar::of($value), $depth],
        };
    }

    /**
     * @param list<mixed> $items
     * @return array{list<JsonValue>, int}
     * @throws InvalidArgumentException
     */
    private function items(array $items, int $depth): array
    {
        $depth = self::deeper($depth, 1);
        $list = [];
        $reached = $depth;
        /** @var mixed $item */
        foreach ($items as $item) {
            [$list[], $itemReached] = $this->child($item, $depth);
            $reached = max($reached, $itemReached);
        }
        return [$list, $reached];
    }

    /**
     * @param array<array-key, mixed> $members
     * @return array{ArrayObject<array-key, JsonValue>, int}
     * @throws InvalidArgumentException
     */
    private function members(array $members, int $depth): array
    {
        $depth = self::deeper($depth, 1);
        $object = [];
        $reached = $depth;
        /** @var mixed $member */
        foreach ($members as $name => $member) {
            CanonicalScalar::assertMemberName($name);
            [$object[$name], $memberReached] = $this->child($member, $depth);
            $reached = max($reached, $memberReached);
        }
        return [new ArrayObject($object), $reached];
    }

    /**
     * Returns a JsonValue for a nested value, with the number of levels that it reaches: a clone of the JsonValue under
     * construction, whose properties aren't set yet, that takes the stored form. The constructor would check that form
     * again. That is why the properties aren't readonly.
     *
     * @return array{JsonValue, int}
     * @throws InvalidArgumentException
     */
    private function child(mixed $value, int $depth): array
    {
        [$stored, $reached] = $this->canonical($value, $depth);
        $child = clone $this;
        $child->value = $stored;
        $child->height = $reached - $depth;
        return [$child, $reached];
    }

    /**
     * Each jsonSerialize() call counts as a level, but the levels reached leave it out again. Exceptions from it become
     * an InvalidArgumentException, so that the constructor throws nothing else.
     *
     * @return array{Stored, int}
     * @throws InvalidArgumentException
     */
    private function serialized(JsonSerializable $value, int $depth): array
    {
        $depth = self::deeper($depth, 1);
        try {
            /** @var mixed $serialized */
            $serialized = $value->jsonSerialize();
        } catch (Throwable $error) {
            throw new InvalidArgumentException(
                sprintf('JsonValue cannot hold %s, because its jsonSerialize() threw.', get_debug_type($value)),
                previous: $error,
            );
        }
        [$stored, $reached] = $this->canonical($serialized, $depth);
        return [$stored, $reached - 1];
    }

    /** @throws InvalidArgumentException If the levels exceed the nesting limit. */
    private static function deeper(int $depth, int $levels): int
    {
        $depth += $levels;
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException(self::TOO_DEEP);
        }
        return $depth;
    }
}
