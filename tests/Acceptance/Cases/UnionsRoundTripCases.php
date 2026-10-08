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
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::unions1();
        yield from self::unions2();
        yield from self::unions3();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function unions1(): iterable
    {
        yield 'collection field union 1param' => [CollectionDeclarationFixture::object(
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
        yield 'scalar union public property with int' => [(static function (): object {
            $object = new UnionPublicProperty();
            $object->value = 42;
            return $object;
        })()];
        yield 'collection non-encodable union list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|true>param0' =>
            [CollectionDeclarationFixture::object(
                'array',
                'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|true>',
                'param',
                [true],
            )];
        yield 'float union preserves actual item types' => [new FloatUnionList([0.0, 3.0, -1.25, null])];
        yield 'collection union int<0, max>|non-empty-string1listparam0' => [CollectionDeclarationFixture::object(
            'array',
            'list<int<0, max>|non-empty-string>',
            'param',
            ['answer'],
        )];
        yield 'collection non-encodable union list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|bool|null>param1' =>
            [CollectionDeclarationFixture::object(
                'array',
                'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|bool|null>',
                'param',
                [StringBackedStatus::Ready],
            )];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function unions2(): iterable
    {
        yield 'collection field union 24param' => [CollectionDeclarationFixture::object(
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
        yield 'collection non-encodable union list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|false>param0' =>
            [CollectionDeclarationFixture::object(
                'array',
                'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|false>',
                'param',
                [false],
            )];
        yield 'different-backed-type enum union public property with Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus::Ready' =>
            [(static function (): object {
                $object = new MultipleEnumUnionPublicProperty();
                $object->value = IntBackedStatus::Ready;
                return $object;
            })()];
        yield 'collection union \Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\eventjet\json\test\acceptance\fixtures\stringbackedstatus|int0listparam0' =>
            [CollectionDeclarationFixture::object(
                'array',
                'list<\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\eventjet\json\test\acceptance\fixtures\stringbackedstatus|int>',
                'param',
                [StringBackedStatus::Ready],
            )];
        yield 'collection non-encodable union list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|bool|null>param0' =>
            [CollectionDeclarationFixture::object(
                'array',
                'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|bool|null>',
                'param',
                [new Coordinates(1.0, 2.0)],
            )];
        yield 'collection field union 12param' => [CollectionDeclarationFixture::object(
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
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function unions3(): iterable
    {
        yield 'collection field union 3param' => [CollectionDeclarationFixture::object(
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
        yield 'collection field union 22param' => [CollectionDeclarationFixture::object(
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
