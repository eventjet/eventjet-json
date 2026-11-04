<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectedMembers;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectedMembersList;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectedMembersProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectorNamedLikeField;
use InvalidArgumentException;
use JsonException;
use Random\Engine\Mt19937;
use Random\Randomizer;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
use function bin2hex;
use function json_encode;
use function sprintf;
use function strval;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Cases for #[OtherMembers]. Generated inputs check the law that a class keeps every member that no declared field
 * claims: decoding collects them under their names, in their order, and encoding writes them back after the declared
 * fields, so the document round-trips exactly.
 *
 * @internal
 */
final class OtherMembersCases
{
    private const int GENERATED = 40;

    /** Member names, including ones that PHP turns into integer array keys and the PHP name of a renamed field. */
    private const array NAMES = [
        'others',
        'id',
        '0',
        '42',
        '-1',
        '01',
        '1e0',
        '',
        ' ',
        'x-vendor',
        'ä',
        "a\0",
        '99999999999999999999',
    ];

    /** The JSON names of the declared fields of CollectedMembers, which the generators leave out. */
    private const array DECLARED = ['$id' => true, 'count' => true, 'extra' => true];

    /**
     * @return iterable<string, array{object}>
     * @throws InvalidArgumentException
     */
    public static function objects(): iterable
    {
        yield 'no other members' => [new CollectedMembers('a')];
        foreach (self::NAMES as $name) {
            yield sprintf('other member named "%s"', $name) => [new CollectedMembers('a', count: 1, others: [
                $name => new JsonValue(['nested' => [1, 'two', null]]),
            ])];
        }
        yield 'every other member name' => [new CollectedMembers('a', others: array_map(
            self::wrap(...),
            array_combine(self::NAMES, self::NAMES),
        ))];
        yield 'other members next to a present JsonValue|null field' => [new CollectedMembers(
            'a',
            new JsonValue(null),
            2,
            ['x' => new JsonValue(true)],
        )];
        yield 'other member named like the PHP name of a renamed field' => [new CollectorNamedLikeField('l', [
            'label' => new JsonValue('collected'),
        ])];
        $property = new CollectedMembersProperty();
        $property->name = 'n';
        $property->others = ['42' => new JsonValue(42), 'name2' => new JsonValue([])];
        yield 'other members in a property outside the constructor' => [$property];
        yield 'other members in nested objects' => [new CollectedMembersList([
            new CollectedMembers('a', others: ['x' => new JsonValue(1)]),
            new CollectedMembers('b'),
            new CollectedMembers('c', others: ['x' => new JsonValue(2), 'y' => new JsonValue(3)]),
        ])];
    }

    /**
     * @return iterable<string, array{string, object}>
     * @throws InvalidArgumentException
     */
    public static function decoded(): iterable
    {
        yield 'unknown members are collected by their JSON names' => [
            '{"$id":"a","id":"legacy","others":{"a":1},"0":null,"42":[1],"":"e"}',
            new CollectedMembers('a', others: [
                'id' => new JsonValue('legacy'),
                'others' => new JsonValue(['a' => 1]),
                '0' => new JsonValue(null),
                '42' => new JsonValue([1]),
                '' => new JsonValue('e'),
            ]),
        ];
        yield 'declared fields are not collected' => [
            '{"count":null,"$id":"a","extra":null}',
            new CollectedMembers('a', new JsonValue(null)),
        ];
        $property = new CollectedMembersProperty();
        $property->others = [];
        yield 'a collecting property without a default value receives an empty array' => ['{}', $property];
        $property = new CollectedMembersProperty();
        $property->name = 'n';
        $property->others = ['x' => new JsonValue(1)];
        yield 'a collecting property outside the constructor' => ['{"x":1,"name":"n"}', $property];
        yield 'renamed field over the name of the collecting property' => [
            '{"others":"label","label":"collected"}',
            new CollectorNamedLikeField('label', ['label' => new JsonValue('collected')]),
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, callable(object): bool}>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public static function documents(): iterable
    {
        for ($seed = 1; $seed <= self::GENERATED; $seed++) {
            yield sprintf('generated other members, seed %d', $seed) => self::generated($seed);
        }
    }

    /**
     * @return array{string, class-string, callable(object): bool}
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private static function generated(int $seed): array
    {
        $random = new Randomizer(new Mt19937($seed));
        $inputs = [];
        for ($count = $random->getInt(0, 5); $count > 0; $count--) {
            $name = self::name($random);
            if (array_key_exists($name, self::DECLARED)) {
                continue;
            }
            $inputs[$name] = JsonValueInputs::value($random, 0);
        }
        $withExtra = $random->getInt(0, 1);
        $extra = $withExtra === 1 ? new JsonValue(JsonValueInputs::value($random, 0)) : null;
        $withCount = $random->getInt(0, 1);
        $original = new CollectedMembers(
            bin2hex($random->getBytes(3)),
            $extra,
            $withCount === 1 ? $random->getInt(PHP_INT_MIN, PHP_INT_MAX) : null,
            array_map(self::wrap(...), $inputs),
        );
        $expected = array_map(JsonValueTrees::reference(...), $inputs);
        $json = json_encode($original, JSON_THROW_ON_ERROR);
        return [
            $json,
            CollectedMembers::class,
            static fn(object $decoded): bool => (
                $decoded instanceof CollectedMembers
                && $decoded->id === $original->id
                && $decoded->count === $original->count
                && ($decoded->extra === null) === ($original->extra === null)
                && array_keys($decoded->others) === array_keys($inputs)
                && JsonValueTrees::members($decoded->others) === $expected
                && json_encode($decoded) === $json
            ),
        ];
    }

    /** A name from the list, or a random one: a string, or a number that PHP turns into an integer key. */
    private static function name(Randomizer $random): string
    {
        $kind = $random->getInt(0, 3);
        return match ($kind) {
            0 => bin2hex($random->getBytes(2)),
            1 => strval($random->getInt(PHP_INT_MIN, PHP_INT_MAX)),
            default => JsonValueInputs::pick($random, self::NAMES),
        };
    }

    /** @throws InvalidArgumentException */
    private static function wrap(mixed $value): JsonValue
    {
        return new JsonValue($value);
    }
}
