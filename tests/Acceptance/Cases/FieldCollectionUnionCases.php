<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function strtolower;

/** @internal */
final class FieldCollectionUnionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::omittedProperties();
        foreach (self::inputs() as $index => [$native, $declaration, $values]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                $objects = [];
                foreach ($values as $value) {
                    $reflection = new ReflectionClass($class);
                    $object = $tag === 'param' ? $reflection->newInstance($value) : $reflection->newInstance();
                    if ($tag === 'var') {
                        $reflection->getProperty('value')->setValue($object, $value);
                    }
                    $objects[] = $object;
                }
                $batchClass = CollectionDeclarationFixture::create('array', 'list<\\' . $class . '>', 'param');
                yield 'collection field union ' . $index . $tag => [new ReflectionClass($batchClass)->newInstance(
                    $objects,
                )];
            }
        }
    }

    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function omittedProperties(): iterable
    {
        foreach ([
            'array|string' => 'list<int>|string',
            '?array' => 'list<int>|null',
            '?\\ArrayObject' => 'ArrayObject<string, int>|null',
        ] as $native => $declaration) {
            $class = CollectionDeclarationFixture::create($native, $declaration, 'var');
            yield 'omitted collection union ' . $native => [new ReflectionClass($class)->newInstance()];
        }
    }

    /** @return list<array{string, string, list<array<array-key, mixed>|bool|float|int|object|string|null>}> */
    private static function inputs(): array
    {
        $person = '\\' . Coordinates::class;
        $enum = '\\' . StringBackedStatus::class;
        return [
            ['array|string', 'list<int>|numeric-string', [[], '42', '']],
            ['array|int', 'list<int>|int<5, max>|non-zero-int', [[], 0, 42]],
            ['?\\arrayobject', 'ArrayObject<string, int>|null', [new ArrayObject(), null]],
            [
                'array|\\' . strtolower(Coordinates::class),
                'list<int>|\\' . Coordinates::class,
                [[], new Coordinates(3.0, 4.5)],
            ],
            [
                '\\arrayobject|string',
                'arrayobject<string, int>|string',
                [new ArrayObject(), new ArrayObject(['01' => 42]), ''],
            ],
            [
                'array|\\' . Coordinates::class,
                'list<int>|\\' . strtolower(Coordinates::class),
                [[], new Coordinates(3.0, 4.5)],
            ],
            ['?array', 'list<int>|null', [[], [0, 1], null]],
            ['?array', 'null|non-empty-array<string, int>', [['01' => 42], null]],
            [
                '?\\ArrayObject',
                'ArrayObject<string, int>|null',
                [new ArrayObject(), new ArrayObject(['01' => 42]), null],
            ],
            ['array|float', 'list<int>|float', [[0], 3.0, -1.5]],
            ['array|true', 'list<int>|true', [[], true]],
            ['array|false', 'list<int>|false', [[], false]],
            ['array|string', 'list<int>|list<int>|string', [[], [1], '']],
            ['array|string', 'array{0: int, 1?: string}|array{int, 1?: string}|string', [[1], [1, ''], '']],
            [
                'array|' . $person . '|null',
                $person . '|null|list<' . $person . '>',
                [new Coordinates(3.0, 4.5), [], null],
            ],
            [
                'array|' . $person . '|null',
                'list<' . $person . '>|' . $person . '|null',
                [[], [new Coordinates(3.0, 4.5)], new Coordinates(3.0, 4.5), null],
            ],
            ['array|string|null', 'string|null|list<int>', [[], [1, 0, -1], '', '42', null]],
            [
                'array|bool|int|float|string|null',
                'list<float>|bool|int|float|string|null',
                [[3.0, -1.5], true, false, 0, 3.0, '', null],
            ],
            [
                'array|' . $enum . '|int|null',
                'non-empty-list<int>|' . $enum . '|int|null',
                [[0, 1], StringBackedStatus::Ready, 42, null],
            ],
            ['array|string', 'array{0: int, 1?: string}|string', [[0], [1, '42'], '']],
            ['array|bool', 'array{}|bool', [[], true, false]],
            ['array|string', 'non-empty-array<string, float>|string', [['01' => 3.0, 'x' => -1.5], '']],
            [
                '\\ArrayObject|int|null',
                'ArrayObject<string, list<int>>|int|null',
                [new ArrayObject(), new ArrayObject(['01' => [0, 1]]), 42, null],
            ],
            [
                'array|\\ArrayObject|null',
                'list<int>|ArrayObject<string, int>|null',
                [[], [1], new ArrayObject(), new ArrayObject(['01' => 42]), null],
            ],
            ['array|string', 'list<int>|non-empty-map<string, int>|string', [[], [1], ['01' => 42], '']],
            ['array|string', 'list<list<int>|null>|string', [[[1], [], null], '']],
        ];
    }
}
