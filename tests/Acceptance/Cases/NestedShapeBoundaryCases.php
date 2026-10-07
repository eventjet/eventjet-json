<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use ReflectionException;
use RuntimeException;

/** @internal */
final class NestedShapeBoundaryCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        yield from NestedTupleFieldUnionCases::objects();

        $inputs = [
            ['list<array{}>', [[], []]],
            ['array{array{}}', [[]]],
            ['ArrayObject<string, array{}>', new ArrayObject(['01' => []])],
            [
                'list<array{0?: list<int>, 1?: ArrayObject<string, int>}>',
                [[], [[]], [[], new ArrayObject()], [[1], new ArrayObject(['01' => 42])]],
            ],
            ['list<array{0?: list<int>|null}>', [[], [null], [[]], [[1]]]],
            ['list<array{int}|null>', [null, [42]]],
            ['list<null|array{int}>', [[42], null]],
            ['list<array{list<int>}|string|null>', [[[]], [[1]], '', null]],
            [
                'list<array{int}|ArrayObject<string, list<int>>|bool>',
                [[42], new ArrayObject(), new ArrayObject(['01' => []]), false],
            ],
            ['list<array{int}|array{0: int}|string>', [[42], '']],
            [
                'array{list<int>|string, ArrayObject<string, array{0?: int}>|null}',
                [[], new ArrayObject(['01' => [], 'x' => [42]])],
            ],
            ['array{0: non-empty-list<int>, 1?: non-empty-array<string, array{bool}>}', [[42]]],
            ['array{0: non-empty-list<int>, 1?: non-empty-array<string, array{bool}>}', [[42], ['01' => [false]]]],
            [
                "array{\n * 0: list<array{int}>,\n * 1?: ArrayObject<string, array{0?: int}>\n * }",
                [[[42]], new ArrayObject()],
            ],
        ];
        foreach ($inputs as $index => [$type, $value]) {
            foreach (['param', 'var'] as $tag) {
                yield 'nested tuple boundary ' . $index . $tag => [NestedCollectionInputs::fixture(
                    $type === 'ArrayObject<string, array{}>' ? 'ArrayObject' : 'tuple',
                    $type,
                    $tag,
                    $value,
                )];
            }
        }

        foreach ([3, 6, 63] as $depth) {
            $type = 'int';
            $value = 42;
            $shape = 'list';
            for ($level = 0; $level < $depth; ++$level) {
                $shape = match ($level % 5) {
                    0 => 'list',
                    1 => 'non-empty-list',
                    2 => 'non-empty-array',
                    3 => 'ArrayObject',
                    default => 'tuple',
                };
                $type = NestedShapeInputs::declaration($shape, $type);
                $value = NestedShapeInputs::wrap($shape, $value);
            }
            foreach (['param', 'var'] as $tag) {
                yield 'mixed shapes at depth ' . $depth . $tag => [NestedCollectionInputs::fixture(
                    $shape,
                    $type,
                    $tag,
                    $value,
                )];
            }
        }
    }
}
