<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
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
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * Turns a PHP value into the form that a JsonValue stores: what json_decode() reads from its json_encode() output, with
 * every nested array and object wrapped in a JsonValue. Rejects values that json_encode() cannot write, or writes as
 * something that json_decode() reads differently.
 *
 * @internal
 */
final class CanonicalJson
{
    /** The nesting limit of json_encode() and json_decode() by default. */
    private const int MAX_DEPTH = 512;

    /**
     * @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
     * @throws InvalidArgumentException
     */
    public static function of(mixed $value): bool|float|int|string|array|ArrayObject|null
    {
        return self::canonical($value, 0);
    }

    /**
     * Returns the stored form of a value at the given nesting depth. The stored form of an array or an object holds
     * JsonValue objects, which the JsonValue constructor takes as they are, so each level is checked once. That also
     * goes for JsonValue objects in the input: their levels don't count towards the nesting limit. json_encode()
     * prefers jsonSerialize() over the backing value of an enum, so this does too.
     *
     * @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
     * @throws InvalidArgumentException
     */
    private static function canonical(mixed $value, int $depth): bool|float|int|string|array|ArrayObject|null
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_string($value) => self::string($value, 'Strings'),
            is_float($value) => self::number($value),
            is_array($value) => array_is_list($value) ? self::items($value, $depth) : self::members($value, $depth),
            $value instanceof JsonSerializable => self::serialized($value, $depth),
            $value instanceof BackedEnum => $value->value,
            $value instanceof stdClass => self::members(get_object_vars($value), $depth),
            $value instanceof ArrayObject => self::members($value->getArrayCopy(), $depth),
            default => throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold %s. It accepts null, scalars, arrays, stdClass, ArrayObject, JsonSerializable objects and backed enums.',
                get_debug_type($value),
            )),
        };
    }

    /**
     * @param list<mixed> $items
     * @return list<JsonValue>
     * @throws InvalidArgumentException
     */
    private static function items(array $items, int $depth): array
    {
        $depth = self::nested($depth);
        $list = [];
        /** @var mixed $item */
        foreach ($items as $item) {
            $list[] = self::child($item, $depth);
        }
        return $list;
    }

    /**
     * @param array<array-key, mixed> $members
     * @return ArrayObject<array-key, JsonValue>
     * @throws InvalidArgumentException
     */
    private static function members(array $members, int $depth): ArrayObject
    {
        $depth = self::nested($depth);
        $object = [];
        /** @var mixed $member */
        foreach ($members as $name => $member) {
            $object[is_string($name) ? self::name($name) : $name] = self::child($member, $depth);
        }
        return new ArrayObject($object);
    }

    /** @throws InvalidArgumentException */
    private static function child(mixed $value, int $depth): JsonValue
    {
        return $value instanceof JsonValue ? $value : new JsonValue(self::canonical($value, $depth));
    }

    /**
     * Each jsonSerialize() call counts as a level, so that one that returns its own object can't recurse forever.
     * Exceptions from it become an InvalidArgumentException, so that the JsonValue constructor throws nothing else.
     *
     * @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
     * @throws InvalidArgumentException
     */
    private static function serialized(
        JsonSerializable $value,
        int $depth,
    ): bool|float|int|string|array|ArrayObject|null {
        $depth = self::nested($depth);
        try {
            /** @var mixed $serialized */
            $serialized = $value->jsonSerialize();
        } catch (Throwable $error) {
            throw new InvalidArgumentException(
                sprintf('JsonValue cannot hold %s, because its jsonSerialize() threw.', get_debug_type($value)),
                previous: $error,
            );
        }
        return self::canonical($serialized, $depth);
    }

    /** @throws InvalidArgumentException */
    private static function nested(int $depth): int
    {
        if ($depth === self::MAX_DEPTH) {
            throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold values nested more than %d levels deep, which json_encode() cannot write. Each jsonSerialize() call counts as a level.',
                self::MAX_DEPTH,
            ));
        }
        return $depth + 1;
    }

    /** @throws InvalidArgumentException */
    private static function name(string $name): string
    {
        self::string($name, 'Member names');
        if (str_starts_with($name, "\0")) {
            throw new InvalidArgumentException(
                'Member names in a JsonValue must not start with a null byte, because json_decode() cannot read them.',
            );
        }
        return $name;
    }

    /**
     * @param string $kind What the string is, for the error message.
     * @throws InvalidArgumentException
     */
    private static function string(string $value, string $kind): string
    {
        $valid = preg_match('//u', $value) === 1;
        if (!$valid) {
            throw new InvalidArgumentException(sprintf(
                '%s in a JsonValue must be valid UTF-8, which json_encode() requires.',
                $kind,
            ));
        }
        return $value;
    }

    /**
     * Returns what json_decode() reads from json_encode() of a finite float: an integer for a whole float that
     * json_encode() writes without an exponent, a float otherwise.
     *
     * @throws InvalidArgumentException
     */
    private static function number(float $value): float|int
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException(sprintf(
                'JsonValue cannot hold the float %s, which has no JSON representation.',
                $value,
            ));
        }
        /** @var float|int */
        return json_decode((string) json_encode($value));
    }
}
