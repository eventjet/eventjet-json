<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Random\Engine\Mt19937;
use Random\Randomizer;

use function array_slice;
use function count;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * `ArrayObject<array-key, T>` and `ArrayObject<int|string, T>` accept every member name. PHP turns names such as `"42"`
 * into integer keys, and `json_encode()` writes them back as member names, because it always writes an `ArrayObject` as
 * a JSON object.
 *
 * @internal
 */
final class AnyKeyMapCases
{
    private const array KEY_TYPES = ['array-key', 'int|string', 'string|int'];

    /** Names that PHP turns into integer keys. */
    private const array INTEGER_NAMES = ['0', '42', '-7', '200', '9223372036854775807', '-9223372036854775808'];

    /** Names that look numeric, but stay string keys. */
    private const array NUMERIC_STRING_NAMES = [
        '01',
        '-0',
        '+1',
        '1e0',
        '1.0',
        ' 1',
        '1 ',
        '0x1A',
        '9223372036854775808',
        '-9223372036854775809',
    ];

    private const array VALUE_TYPES = ['int', 'string', 'float', 'bool', 'list<int>', '\\' . Person::class];

    private const array OTHER_NAMES = ['', 'a', 'default', 'Grüße', '😀', 'a b', '"', '\\', '[0]', '$ref'];

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        $random = new Randomizer(new Mt19937(20_261_102));
        foreach (self::VALUE_TYPES as $type) {
            for ($round = 0; $round < 8; $round++) {
                $map = [];
                for ($size = $random->getInt(0, 6); $size > 0; $size--) {
                    $map[self::name($random)] = self::value($random, $type);
                }
                $declaration = 'ArrayObject<' . self::pick($random, self::KEY_TYPES) . ', ' . $type . '>';
                $tag = self::pick($random, ['param', 'var']);
                yield 'generated any-key map ' . $round . ': ' . $declaration . ' in ' . $tag => [
                    CollectionDeclarationFixture::object('\\ArrayObject', $declaration, $tag, new ArrayObject($map)),
                ];
            }
        }
        yield 'any-key map nested in an any-key map' => [CollectionDeclarationFixture::object(
            '\\ArrayObject',
            'ArrayObject<array-key, ArrayObject<array-key, int>>',
            'param',
            new ArrayObject([200 => new ArrayObject([0 => 1, '01' => 2]), 'b' => new ArrayObject()]),
        )];
        yield 'nullable any-key map preserves its integer keys' => [CollectionDeclarationFixture::object(
            '?\\ArrayObject',
            'ArrayObject<array-key, string>|null',
            'var',
            new ArrayObject([0 => 'x', 42 => 'y']),
        )];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string|JsonType<list<mixed>|object>, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['ArrayObject<array-key, int>', 'ArrayObject<array-key, \\' . Person::class . '>'] as $declaration) {
            $class = CollectionDeclarationFixture::create('\\ArrayObject', $declaration, 'param');
            yield $declaration . ' names an integer key in a value error' => [
                '{"value":{"200":"text"}}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value[200] must be of type '
                    . ($declaration === 'ArrayObject<array-key, int>' ? 'int' : Person::class)
                    . ', string given.',
                DecodeErrorKind::InvalidValue,
            ];
        }
        $class = CollectionDeclarationFixture::create('\\ArrayObject', 'ArrayObject<string, int>', 'param');
        yield 'ArrayObject<string, T> still rejects a name that PHP converts to an integer key' => [
            '{"value":{"a":1,"42":2}}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value has numeric-looking member name 42, which PHP converts to an integer array key. Only ArrayObject<array-key, TValue> fields accept such member names.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'JsonType::map() still rejects a name that PHP converts to an integer key' => [
            '{"200":{}}',
            JsonType::map(EmptyObject::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject from the JSON object: Field  has numeric-looking member name 200, which PHP converts to an integer array key. Only ArrayObject<array-key, TValue> fields accept such member names.',
            DecodeErrorKind::InvalidValue,
        ];
        foreach ([
            'ArrayObject<int|string|bool, string>',
            'ArrayObject<int|int, string>',
            'ArrayObject<string>',
        ] as $declaration) {
            $class = CollectionDeclarationFixture::create('\\ArrayObject', $declaration, 'var');
            yield 'unsupported ArrayObject declaration ' . $declaration => [
                '{}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue>, ArrayObject<string, TValue>, or ArrayObject<array-key, TValue>.',
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        foreach (['ArrayObject<array-key, array>', 'ArrayObject<int|string, array>'] as $declaration) {
            $class = CollectionDeclarationFixture::create('\\ArrayObject', $declaration, 'var');
            yield 'any-key map with an unrecognized value declaration ' . $declaration => [
                '{}',
                $class,
                PhpDocReadingCases::unrecognized($class, 'var'),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
    }

    private static function name(Randomizer $random): string
    {
        $kind = $random->getInt(0, 3);
        return match ($kind) {
            0 => self::pick($random, self::INTEGER_NAMES),
            1 => self::pick($random, self::NUMERIC_STRING_NAMES),
            2 => self::pick($random, self::OTHER_NAMES),
            default => (string) $random->getInt(-1000, 1000),
        };
    }

    /** @return int|string|float|bool|list<int>|Person */
    private static function value(Randomizer $random, string $type): int|string|float|bool|array|Person
    {
        return match ($type) {
            'int' => $random->getInt(PHP_INT_MIN, PHP_INT_MAX),
            'string' => self::pick($random, ['', 'a', '42', 'Grüße', '😀']),
            'float' => $random->getInt(-1000, 1000) / 8,
            'bool' => $random->getInt(0, 1) === 1,
            'list<int>' => array_slice([$random->getInt(-9, 9), $random->getInt(-9, 9)], $random->getInt(0, 2)),
            default => new Person('first', 'last', null, $random->getInt(0, 99)),
        };
    }

    /** @param non-empty-list<string> $items */
    private static function pick(Randomizer $random, array $items): string
    {
        return $items[$random->getInt(0, count($items) - 1)] ?? $items[0];
    }
}
