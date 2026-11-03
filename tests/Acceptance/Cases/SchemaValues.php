<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonValue;
use Eventjet\Json\Schema\Type;
use InvalidArgumentException;
use Random\Randomizer;
use ReflectionException;
use RuntimeException;

use function bin2hex;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Generates the values of schema keywords from a seeded Randomizer, for SchemaInputs.
 *
 * @internal
 */
final class SchemaValues
{
    private const array STRINGS = ['', 'a', 'ä', '😀', "line\nbreak", '#/$defs/a', '^[a-z]+$', 'null', '1'];

    /** Member names, including ones that PHP turns into integer array keys. */
    private const array NAMES = ['a', '', '0', '200', '-1', '01', '$ref', 'ä'];

    /** Numbers that json_encode() writes in a form that json_decode() reads back with the same PHP type. */
    private const array NUMBERS = [0, 1, -1, 42, PHP_INT_MAX, PHP_INT_MIN, 0.5, -2.5, 1e-7, 1.5e300];

    public static function string(Randomizer $random): string
    {
        return JsonValueInputs::pick($random, [...self::STRINGS, bin2hex($random->getBytes(4))]);
    }

    public static function bool(Randomizer $random): bool
    {
        return $random->getInt(0, 1) === 1;
    }

    public static function number(Randomizer $random): int|float
    {
        return JsonValueInputs::pick($random, [
            ...self::NUMBERS,
            $random->getInt(PHP_INT_MIN, PHP_INT_MAX),
            (float) $random->getInt(-1_000_000, 1_000_000) + 0.25,
        ]);
    }

    /** @return Type|list<Type> */
    public static function type(Randomizer $random): Type|array
    {
        $single = self::bool($random);
        if ($single) {
            return JsonValueInputs::pick($random, Type::cases());
        }
        $types = [];
        for ($count = $random->getInt(0, 3); $count > 0; $count--) {
            $types[] = JsonValueInputs::pick($random, Type::cases());
        }
        return $types;
    }

    /**
     * The items of a keyword that holds a list.
     *
     * @param class-string $draft
     * @return list<mixed>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function items(Randomizer $random, string $draft, string $keyword, int $depth): array
    {
        $items = [];
        for ($count = $random->getInt(0, 3); $count > 0; $count--) {
            $items[] = self::item($random, $draft, $keyword, $depth);
        }
        return $items;
    }

    /**
     * The members of a keyword that holds a map.
     *
     * @param class-string $draft
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function members(Randomizer $random, string $draft, string $keyword, int $depth): array
    {
        $members = [];
        for ($count = $random->getInt(0, 3); $count > 0; $count--) {
            $members[JsonValueInputs::pick($random, self::NAMES)] = self::item($random, $draft, $keyword, $depth);
        }
        return $members;
    }

    /**
     * @param class-string $draft
     * @return object|bool|string|list<mixed>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function item(
        Randomizer $random,
        string $draft,
        string $keyword,
        int $depth,
    ): object|bool|string|array {
        return match ($keyword) {
            'enum', 'examples' => new JsonValue(JsonValueInputs::value($random, 0)),
            'required' => JsonValueInputs::pick($random, self::STRINGS),
            'vocabulary' => self::bool($random),
            'dependentRequired' => self::items($random, $draft, 'required', $depth),
            'dependencies' => self::dependency($random, $draft, $depth),
            default => SchemaInputs::subschema($random, $draft, $depth),
        };
    }

    /**
     * @param class-string $draft
     * @return object|bool|list<mixed>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function dependency(Randomizer $random, string $draft, int $depth): object|bool|array
    {
        $asStringList = self::bool($random);
        return $asStringList
            ? self::items($random, $draft, 'required', $depth)
            : SchemaInputs::subschema($random, $draft, $depth);
    }
}
