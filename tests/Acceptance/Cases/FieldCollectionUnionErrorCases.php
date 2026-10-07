<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use RuntimeException;

/** @internal */
final class FieldCollectionUnionErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        yield from CollectionUnionRegressionErrors::errors();
        yield from NestedCollectionUnionErrorCases::errors();
        yield from FieldUnionDeclarationErrors::errors();
        $invalid = [
            [
                'array|string',
                'list<int>|string',
                '{"value":["42"]}',
                'Field value[0] must be of type int, string given.',
            ],
            [
                'array|string',
                'list<int>|string',
                '{"value":false}',
                'Field value must be of type list<int>|string, bool given.',
            ],
            [
                'array|string',
                'non-empty-list<int>|string',
                '{"value":[]}',
                'Field value must be of type non-empty-list<int>, empty list given.',
            ],
            [
                'array|string',
                'array{int}|string',
                '{"value":["42"]}',
                'Field value[0] must be of type int, string given.',
            ],
        ];
        foreach ($invalid as $index => [$native, $declaration, $json, $reason]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                yield 'invalid collection union value ' . $index . $tag => [
                    $json,
                    $class,
                    'Could not create ' . $class . ' from the JSON object: ' . $reason,
                    3,
                ];
            }
        }
        foreach ([
            ['array|string', 'list<int>|list<string>|string'],
            ['array|string', 'list<int>|array{int}|string'],
            ['array|\\' . Coordinates::class, 'non-empty-array<string, int>|\\' . Coordinates::class],
            ['\\ArrayObject|\\' . Coordinates::class, 'ArrayObject<string, int>|\\' . Coordinates::class],
            ['array|\\ArrayObject', 'non-empty-array<string, int>|ArrayObject<string, int>'],
        ] as $index => [$native, $declaration]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                foreach (['{}', '{"value":[]}'] as $json) {
                    yield 'ambiguous collection field union ' . $index . $tag . $json => [
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
