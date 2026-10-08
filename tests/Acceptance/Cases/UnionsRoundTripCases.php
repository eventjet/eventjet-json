<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\FloatUnionList;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty;

/** @internal */
final class UnionsRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::scalarAndEnumMembers();
        yield from self::encodableMembers();
        yield from self::listAndScalarMembers();
        yield from self::objectAndMapMembers();
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function scalarAndEnumMembers(): iterable
    {
        yield 'scalar union property preserves an integer' => [(static function (): object {
            $object = new UnionPublicProperty();
            $object->value = 42;
            return $object;
        })()];
        yield 'float union preserves actual item types' => [new FloatUnionList([0.0, 3.0, -1.25, null])];
        yield 'integer-or-string list preserves a non-empty string' => [CollectionDeclarationFixture::object(
            'array',
            'list<int<0, max>|non-empty-string>',
            'param',
            ['answer'],
        )];
        yield 'enum union property distinguishes integer and string backing types' => [(static function (): object {
            $object = new MultipleEnumUnionPublicProperty();
            $object->value = IntBackedStatus::Ready;
            return $object;
        })()];
        yield 'enum union treats differently cased class names as one type' => [CollectionDeclarationFixture::object(
            'array',
            'list<\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\eventjet\json\test\acceptance\fixtures\stringbackedstatus|int>',
            'param',
            [StringBackedStatus::Ready],
        )];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function encodableMembers(): iterable
    {
        yield 'non-backed-enum-or-true list preserves true' => [CollectionDeclarationFixture::object(
            'array',
            'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|true>',
            'param',
            [true],
        )];
        yield 'mixed encodable union restores a backed enum' => [CollectionDeclarationFixture::object(
            'array',
            'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|bool|null>',
            'param',
            [StringBackedStatus::Ready],
        )];
        yield 'non-backed-enum-or-false list preserves false' => [CollectionDeclarationFixture::object(
            'array',
            'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|false>',
            'param',
            [false],
        )];
        yield 'mixed encodable union restores a final class' => [CollectionDeclarationFixture::object(
            'array',
            'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|bool|null>',
            'param',
            [new Coordinates(1.0, 2.0)],
        )];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function listAndScalarMembers(): iterable
    {
        yield 'list-or-integer fields preserve empty lists and integer values' => [CollectionDeclarationFixture::object(
            'array',
            'list<\\'
            . CollectionDeclarationFixture::create('array|int', 'list<int>|int<5, max>|non-zero-int', 'param')
            . '>',
            'param',
            [
                CollectionDeclarationFixture::object('array|int', 'list<int>|int<5, max>|non-zero-int', 'param', []),
                CollectionDeclarationFixture::object('array|int', 'list<int>|int<5, max>|non-zero-int', 'param', 0),
                CollectionDeclarationFixture::object('array|int', 'list<int>|int<5, max>|non-zero-int', 'param', 42),
            ],
        )];
        yield 'list, map, and string fields retain their distinct shapes' => [CollectionDeclarationFixture::object(
            'array',
            'list<\\'
            . CollectionDeclarationFixture::create(
                'array|string',
                'list<int>|non-empty-map<string, int>|string',
                'param',
            )
            . '>',
            'param',
            [
                CollectionDeclarationFixture::object(
                    'array|string',
                    'list<int>|non-empty-map<string, int>|string',
                    'param',
                    [],
                ),
                CollectionDeclarationFixture::object(
                    'array|string',
                    'list<int>|non-empty-map<string, int>|string',
                    'param',
                    [1],
                ),
                CollectionDeclarationFixture::object(
                    'array|string',
                    'list<int>|non-empty-map<string, int>|string',
                    'param',
                    ['01' => 42],
                ),
                CollectionDeclarationFixture::object(
                    'array|string',
                    'list<int>|non-empty-map<string, int>|string',
                    'param',
                    '',
                ),
            ],
        )];
        yield 'duplicate list alternatives preserve lists and strings' => [CollectionDeclarationFixture::object(
            'array',
            'list<\\'
            . CollectionDeclarationFixture::create('array|string', 'list<int>|list<int>|string', 'param')
            . '>',
            'param',
            [
                CollectionDeclarationFixture::object('array|string', 'list<int>|list<int>|string', 'param', []),
                CollectionDeclarationFixture::object('array|string', 'list<int>|list<int>|string', 'param', [1]),
                CollectionDeclarationFixture::object('array|string', 'list<int>|list<int>|string', 'param', ''),
            ],
        )];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function objectAndMapMembers(): iterable
    {
        yield 'list-or-object field matches class names without regard to case' =>
            [CollectionDeclarationFixture::object(
                'array',
                'list<\\'
                . CollectionDeclarationFixture::create(
                    'array|\eventjet\json\test\acceptance\fixtures\coordinates',
                    'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                    'param',
                )
                . '>',
                'param',
                [
                    CollectionDeclarationFixture::object(
                        'array|\eventjet\json\test\acceptance\fixtures\coordinates',
                        'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                        'param',
                        [],
                    ),
                    CollectionDeclarationFixture::object(
                        'array|\eventjet\json\test\acceptance\fixtures\coordinates',
                        'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                        'param',
                        new Coordinates(3.0, 4.5),
                    ),
                ],
            )];
        yield 'map-or-integer-or-null fields preserve each alternative' => [CollectionDeclarationFixture::object(
            'array',
            'list<\\'
            . CollectionDeclarationFixture::create(
                '\ArrayObject|int|null',
                'ArrayObject<string, list<int>>|int|null',
                'param',
            )
            . '>',
            'param',
            [
                CollectionDeclarationFixture::object(
                    '\ArrayObject|int|null',
                    'ArrayObject<string, list<int>>|int|null',
                    'param',
                    new ArrayObject([]),
                ),
                CollectionDeclarationFixture::object(
                    '\ArrayObject|int|null',
                    'ArrayObject<string, list<int>>|int|null',
                    'param',
                    new ArrayObject(['01' => [0, 1]]),
                ),
                CollectionDeclarationFixture::object(
                    '\ArrayObject|int|null',
                    'ArrayObject<string, list<int>>|int|null',
                    'param',
                    42,
                ),
                CollectionDeclarationFixture::object(
                    '\ArrayObject|int|null',
                    'ArrayObject<string, list<int>>|int|null',
                    'param',
                    null,
                ),
            ],
        )];
    }
}
