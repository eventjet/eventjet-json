<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use RuntimeException;

/** @internal */
final class NestedCollectionUnionErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            ['list<int>|string', '[false]', 'Field value[0] must be of type list<int>|string, bool given.'],
            ['list<int>|string', '[["42"]]', 'Field value[0][0] must be of type int, string given.'],
            ['list<list<int>|string>|bool', '[[["42"]]]', 'Field value[0][0][0] must be of type int, string given.'],
            [
                'ArrayObject<string, int>|string',
                '[[]]',
                'Field value[0] must be of type ArrayObject<string, int>|string, array given.',
            ],
            [
                'ArrayObject<string, int>|list<int>|string',
                '[{"a.b":"42"}]',
                'Field value[0]["a.b"] must be of type int, string given.',
            ],
            [
                'non-empty-list<int>|string',
                '[[]]',
                'Field value[0] must be of type non-empty-list<int>, empty list given.',
            ],
        ] as $index => [$union, $json, $reason]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', 'list<' . $union . '>', $tag);
                yield 'nested collection union value ' . $index . $tag => [
                    '{"value":' . $json . '}',
                    $class,
                    'Could not create ' . $class . ' from the JSON object: ' . $reason,
                    3,
                ];
            }
        }
        foreach ([
            'list<int>|list<string>',
            'int|list<int>|list<string>',
            'non-empty-array<string, int>|ArrayObject<string, int>',
            'ArrayObject<string, int>|\\' . Coordinates::class,
        ] as $union) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', 'list<' . $union . '>', $tag);
                foreach (['{}', '{"value":[]}'] as $json) {
                    yield 'nested ambiguous union ' . $union . $tag . $json => [
                        $json,
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                        3,
                    ];
                }
            }
        }
    }
}
