<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use JsonException;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class NestedShapeErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (NestedShapeInputs::shapes() as $outer) {
            foreach (NestedShapeInputs::shapes() as $inner) {
                $type = NestedShapeInputs::declaration($outer, NestedShapeInputs::declaration($inner, 'int'));
                $value = NestedShapeInputs::wrap($outer, NestedShapeInputs::wrap($inner, '42'));
                $path = 'value' . NestedShapeInputs::path($outer) . NestedShapeInputs::path($inner);
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create(
                        $outer === 'ArrayObject' ? '\\ArrayObject' : 'array',
                        $type,
                        $tag,
                    );
                    yield 'nested shapes leaf mismatch ' . $type . $tag => [
                        '{"value":' . json_encode($value, JSON_THROW_ON_ERROR) . '}',
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field '
                            . $path
                            . ' must be of type int, string given.',
                        3,
                    ];
                }
            }
        }
        foreach (self::invalidValues() as $index => [$type, $json, $reason]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', $type, $tag);
                yield 'nested tuple invalid value ' . $index . $tag => [
                    '{"value":' . $json . '}',
                    $class,
                    'Could not create ' . $class . ' from the JSON object: Field ' . $reason,
                    3,
                ];
            }
        }
        yield from NestedShapeDeclarationErrors::errors();
    }

    /** @return list<array{string, string, string}> */
    private static function invalidValues(): array
    {
        return [
            ['list<array{int}>', '[{}]', 'value[0] must be of type array{int}, stdClass given.'],
            ['list<array{int}>', '[null]', 'value[0] must be of type array{int}, null given.'],
            ['list<array{int}>', '[{"0":42}]', 'value[0] must be of type array{int}, stdClass given.'],
            ['list<array{int}>', '[[]]', 'value[0] must be of type array{int} with exactly 1 items, 0 given.'],
            ['list<array{int}>', '[[42,43]]', 'value[0] must be of type array{int} with exactly 1 items, 2 given.'],
            [
                'list<array{0?: list<int>}>',
                '[[[],[]]]',
                'value[0] must be of type array{0?: list<int>} with between 0 and 1 items, 2 given.',
            ],
            ['list<array{0?: list<int>}>', '[[null]]', 'value[0][0] must be of type list<int>, null given.'],
            ['list<array{int}|null>', '[false]', 'value[0] must be of type array{int}, bool given.'],
            ['list<null|array{int}>', '[false]', 'value[0] must be of type array{int}, bool given.'],
            ['list<array{int}|string>', '[false]', 'value[0] must be of type array{0: int}|string, bool given.'],
            ['array{list<int>}', '[{}]', 'value[0] must be of type list<int>, stdClass given.'],
            ['array{non-empty-list<int>}', '[[]]', 'value[0] must be of type non-empty-list<int>, empty list given.'],
            ['array{ArrayObject<string, int>}', '[[]]', 'value[0] must be of type JSON object, array given.'],
            [
                'array{ArrayObject<string, int>}',
                '[{"0":42}]',
                'value[0] has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            ],
            [
                'array{non-empty-array<string, int>}',
                '[{}]',
                'value[0] uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            ],
            ['array{list<int>|string}', '[false]', 'value[0] must be of type list<int>|string, bool given.'],
            ['array{array{int}}', '[]', 'value must be of type array{array{0: int}} with exactly 1 items, 0 given.'],
            [
                'array{0?: ArrayObject<string, array{int}>}',
                '[{"a.b":["42"]}]',
                'value[0]["a.b"][0] must be of type int, string given.',
            ],
            ['array{array{}}', '[[1]]', 'value[0] must be of type array{} with exactly 0 items, 1 given.'],
        ];
    }
}
