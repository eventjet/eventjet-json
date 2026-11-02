<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
use Closure;
use Eventjet\Json\JsonValue;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use Throwable;

use function array_is_list;
use function get_debug_type;
use function get_object_vars;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * Turns a PHP value into the form that a JsonValue stores: what json_decode() reads from its json_encode() output, with
 * every nested array and object wrapped in a JsonValue. Rejects values that json_encode() cannot write, or writes as
 * something that json_decode() reads differently.
 *
 * Each level is checked once: nested JsonValue objects take their stored form without checking it again. Every
 * JsonValue also stores its height, the number of levels of arrays and objects in it, so that JsonValue objects in the
 * input count towards the nesting limit.
 *
 * @psalm-type Stored = bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
 * @phpstan-type Stored bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
 * @internal
 */
final class CanonicalJson
{
    /** The nesting limit of json_encode() and json_decode() by default. */
    private const int MAX_DEPTH = 512;

    private const string TOO_DEEP =
        'JsonValue cannot hold values nested more than '
            . self::MAX_DEPTH
            . ' levels deep, which json_encode() cannot write. Each jsonSerialize() call counts as a level.';

    /**
     * The stored form for the JsonValue that child() constructs. The constructor passes it on before any other code
     * runs.
     *
     * @var array{Stored, int}|null
     */
    private static array|null $pending = null;

    /**
     * Returns the stored form of a value and its height.
     *
     * @return array{Stored, int}
     * @throws InvalidArgumentException
     */
    public static function of(mixed $value): array
    {
        $pending = self::$pending;
        self::$pending = null;
        return $pending ?? self::canonical($value, 0);
    }

    /**
     * Returns the stored form of a value below the given number of levels, and the number of levels that it reaches.
     * The levels count arrays, objects and jsonSerialize() calls, so that a jsonSerialize() method that returns its own
     * object can't recurse forever. Heights leave out the jsonSerialize() calls, because the stored form has none.
     * json_encode() prefers jsonSerialize() over the backing value of an enum, so this does too.
     *
     * @return array{Stored, int}
     * @throws InvalidArgumentException
     */
    private static function canonical(mixed $value, int $depth): array
    {
        return match (true) {
            is_array($value) => array_is_list($value) ? self::items($value, $depth) : self::members($value, $depth),
            $value instanceof JsonSerializable => self::serialized($value, $depth),
            $value instanceof stdClass => self::members(get_object_vars($value), $depth),
            $value instanceof ArrayObject => self::members($value->getArrayCopy(), $depth),
            default => [self::scalar($value), $depth],
        };
    }

    /**
     * Returns what json_decode() reads from json_encode() of a scalar, null or a backed enum. That is an integer for a
     * whole float that json_encode() writes without an exponent.
     *
     * @throws InvalidArgumentException
     */
    private static function scalar(mixed $value): bool|float|int|string|null
    {
        if (is_string($value)) {
            self::assertUtf8($value, 'Strings');
            return $value;
        }
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold the float %s, which has no JSON representation.',
                $value,
            ));
        }
        /** @var bool|float|int|string|null */
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_float($value) => json_decode((string) json_encode($value)),
            $value instanceof BackedEnum => $value->value,
            default => throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold %s. It accepts null, scalars, arrays, stdClass, ArrayObject, JsonSerializable objects and backed enums.',
                get_debug_type($value),
            )),
        };
    }

    /**
     * @param list<mixed> $items
     * @return array{list<JsonValue>, int}
     * @throws InvalidArgumentException
     */
    private static function items(array $items, int $depth): array
    {
        $depth = self::deeper($depth, 1);
        $list = [];
        $reached = $depth;
        /** @var mixed $item */
        foreach ($items as $item) {
            [$list[], $itemReached] = self::child($item, $depth);
            $reached = max($reached, $itemReached);
        }
        return [$list, $reached];
    }

    /**
     * @param array<array-key, mixed> $members
     * @return array{ArrayObject<array-key, JsonValue>, int}
     * @throws InvalidArgumentException
     */
    private static function members(array $members, int $depth): array
    {
        $depth = self::deeper($depth, 1);
        $object = [];
        $reached = $depth;
        /** @var mixed $member */
        foreach ($members as $name => $member) {
            if (is_string($name)) {
                self::assertUtf8($name, 'Member names');
            }
            if (is_string($name) && str_starts_with($name, "\0")) {
                throw new InvalidArgumentException(
                    'Member names in a JsonValue must not start with a null byte, because json_decode() cannot read them.',
                );
            }
            [$object[$name], $memberReached] = self::child($member, $depth);
            $reached = max($reached, $memberReached);
        }
        return [new ArrayObject($object), $reached];
    }

    /**
     * Returns a JsonValue for a nested value, with the number of levels that it reaches: a JsonValue as it is, or a
     * new one that takes the stored form through of(), which would otherwise check that form again.
     *
     * @return array{JsonValue, int}
     * @throws InvalidArgumentException
     */
    private static function child(mixed $value, int $depth): array
    {
        if ($value instanceof JsonValue) {
            return [$value, self::deeper($depth, self::height($value))];
        }
        [$stored, $reached] = self::canonical($value, $depth);
        self::$pending = [$stored, $reached - $depth];
        return [new JsonValue(null), $reached];
    }

    /**
     * JsonValue keeps its height private, so this reads it with a closure bound to its scope. Psalm needs
     * @psalm-scope-this to know that scope.
     */
    private static function height(JsonValue $value): int
    {
        /**
         * @psalm-scope-this JsonValue
         * @var Closure(JsonValue): int $read
         */
        $read = Closure::bind(static fn(JsonValue $value): int => $value->height, null, JsonValue::class);
        return $read($value);
    }

    /**
     * Each jsonSerialize() call counts as a level, but the levels reached leave it out again. Exceptions from it become
     * an InvalidArgumentException, so that the JsonValue constructor throws nothing else.
     *
     * @return array{Stored, int}
     * @throws InvalidArgumentException
     */
    private static function serialized(JsonSerializable $value, int $depth): array
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
        [$stored, $reached] = self::canonical($serialized, $depth);
        return [$stored, $reached - 1];
    }

    /**
     * Returns the number of levels after adding more.
     *
     * @throws InvalidArgumentException If that exceeds the nesting limit.
     */
    private static function deeper(int $depth, int $levels): int
    {
        $depth += $levels;
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException(self::TOO_DEEP);
        }
        return $depth;
    }

    /**
     * @param string $kind What the string is, for the error message.
     * @throws InvalidArgumentException
     */
    private static function assertUtf8(string $value, string $kind): void
    {
        $valid = preg_match('//u', $value) === 1;
        if (!$valid) {
            throw new InvalidArgumentException(sprintf(
                '%s in a JsonValue must be valid UTF-8, which json_encode() requires.',
                $kind,
            ));
        }
    }
}
