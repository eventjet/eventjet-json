<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use BackedEnum;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\SerializedJson;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use InvalidArgumentException;
use Random\Randomizer;
use stdClass;

use function bin2hex;
use function count;

use const PHP_FLOAT_EPSILON;
use const PHP_FLOAT_MAX;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Generates PHP values that JsonValue accepts, in each of the forms that it accepts, from a seeded Randomizer.
 *
 * @internal
 */
final class JsonValueInputs
{
    private const int MAX_DEPTH = 4;

    private const array SCALARS = [
        null,
        true,
        false,
        0,
        1,
        -1,
        42,
        PHP_INT_MAX,
        PHP_INT_MIN,
        1.0,
        -0.0,
        0.0,
        0.1,
        1.5,
        -2.5,
        1e15,
        1e19,
        -1e18,
        5e-324,
        PHP_FLOAT_EPSILON,
        PHP_FLOAT_MAX,
        9_007_199_254_740_993.0,
        '',
        'a',
        'ä',
        '😀',
        "\0",
        "line\nbreak",
        '"quoted"',
        '\\',
        '1',
        '1.0',
        'null',
    ];

    /** Member names, including ones that PHP turns into integer array keys. */
    private const array NAMES = ['a', '', '0', '42', '-1', '01', '1e0', 'ä', ' ', "a\0"];

    /** Member names that stay strings, for `ArrayObject<string, T>` maps. */
    public const array STRING_NAMES = ['a', 'b', '', '01', '1e0', 'ä', ' '];

    /**
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws InvalidArgumentException
     */
    public static function value(Randomizer $random, int $depth): array|bool|float|int|object|string|null
    {
        $kind = $random->getInt(0, $depth < self::MAX_DEPTH ? 8 : 2);
        $depth++;
        return match ($kind) {
            0 => self::scalar($random),
            1 => [],
            2 => new stdClass(),
            3 => self::listOf($random, $depth, 0),
            4 => self::members($random, $depth),
            5 => (object) self::members($random, $depth),
            6 => new ArrayObject(self::members($random, $depth)),
            7 => new SerializedJson(self::value($random, $depth)),
            default => new JsonValue(self::value($random, $depth)),
        };
    }

    /**
     * @return list<array<array-key, mixed>|bool|float|int|object|string|null>
     * @throws InvalidArgumentException
     */
    public static function listOf(Randomizer $random, int $depth, int $min): array
    {
        $items = [];
        for ($count = $random->getInt($min, 3); $count > 0; $count--) {
            $items[] = self::value($random, $depth);
        }
        return $items;
    }

    /**
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>
     * @throws InvalidArgumentException
     */
    public static function mapOf(Randomizer $random): array
    {
        $map = [];
        for ($count = $random->getInt(0, 3); $count > 0; $count--) {
            $map[self::pick($random, self::STRING_NAMES)] = self::value($random, 0);
        }
        return $map;
    }

    /**
     * @template T
     * @param non-empty-list<T> $values
     * @return T
     */
    public static function pick(Randomizer $random, array $values): mixed
    {
        return $values[$random->getInt(0, count($values) - 1)] ?? $values[0];
    }

    /** A scalar, null or a backed enum: an interesting one, or a random one. */
    private static function scalar(Randomizer $random): BackedEnum|bool|float|int|string|null
    {
        return self::pick($random, [
            ...self::SCALARS,
            StringBackedStatus::Ready,
            IntBackedStatus::Pending,
            JsonSerializableStatus::Ready,
            $random->getInt(PHP_INT_MIN, PHP_INT_MAX),
            $random->getFloat(-1e6, 1e6),
            bin2hex($random->getBytes(4)),
        ]);
    }

    /**
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>
     * @throws InvalidArgumentException
     */
    private static function members(Randomizer $random, int $depth): array
    {
        $members = [];
        for ($count = $random->getInt(1, 3); $count > 0; $count--) {
            $members[self::pick($random, self::NAMES)] = self::value($random, $depth);
        }
        return $members;
    }
}
