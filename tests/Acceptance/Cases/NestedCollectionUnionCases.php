<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionException;
use RuntimeException;

/** @internal */
final class NestedCollectionUnionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        $shapes = ['list', 'non-empty-list', 'non-empty-array', 'ArrayObject'];
        foreach (self::inputs() as $index => [$union, $values]) {
            $rotatedShape = match ($index % 4) {
                0 => 'list',
                1 => 'non-empty-list',
                2 => 'non-empty-array',
                default => 'ArrayObject',
            };
            foreach ($index === 0 ? $shapes : [$rotatedShape] as $shape) {
                $type = NestedCollectionInputs::declaration($shape, $union);
                $value = NestedCollectionInputs::wrap($shape, $values);
                foreach (['param', 'var'] as $tag) {
                    yield 'nested collection union ' . $type . $tag => [NestedCollectionInputs::fixture(
                        $shape,
                        $type,
                        $tag,
                        $value,
                    )];
                }
            }
        }
    }

    /** @return list<array{string, list<array<array-key, mixed>|bool|float|int|object|string|null>}> */
    private static function inputs(): array
    {
        $class = '\\' . Coordinates::class;
        $enum = '\\' . StringBackedStatus::class;
        return [
            ['list<int>|string|null', [[], [0, 1], '', '42', null]],
            ['null|string|list<int>', [null, '', [], [1]]],
            ['non-empty-list<float>|bool', [[3.0, -1.5], true, false]],
            [
                'list<' . $class . '>|' . $class . '|null',
                [[], [new Coordinates(3.0, 4.5)], new Coordinates(3.0, 4.5), null],
            ],
            ['list<int>|' . $enum . '|int|null', [[], [1], StringBackedStatus::Ready, 0, null]],
            ['non-empty-array<string, int>|bool|null', [['01' => 42], true, false, null]],
            ['ArrayObject<string, int>|string', [new ArrayObject(), new ArrayObject(['01' => 42]), '']],
            [
                'list<int>|ArrayObject<string, float>|bool',
                [[], [0, 1], new ArrayObject(), new ArrayObject(['01' => 3.0]), true],
            ],
            ['list<int>|non-empty-map<string, bool>|int', [[], [1], ['01' => false], 0]],
            ['list<list<int>|string>|bool', [[[], [1], ''], false]],
            ['list<int>|list<int>|string', [[], [1], '']],
            ['int|list<numeric-string>', [0, [], ['42', '']]],
            ['list<int>|float', [[], [0], 3.0, -1.5]],
            ['list<int>|true|false', [[], [1], true, false]],
        ];
    }
}
