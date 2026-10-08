<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\TupleFields;

/** @internal */
final class TupleValueErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::itemCounts();
        yield from self::itemTypesAndShapes();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function itemCounts(): iterable
    {
        yield 'empty tuple rejects an item' => [
            '{"value":[1,"text",3,true,"ready",1,{"firstName":"Ada","lastName":"Lovelace","middleName":null,"age":null}],"empty":[0]}',
            new TupleFields([
                1,
                'text',
                3.0,
                true,
                StringBackedStatus::Ready,
                IntBackedStatus::Ready,
                new Person('Ada', 'Lovelace'),
            ])::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\TupleFields from the JSON object: Field empty must be of type array{} with exactly 0 items, 1 given.',
            3,
        ];
        yield 'optional nested tuple rejects excess items' => [
            '{"value":[[[],[]]]}',
            CollectionDeclarationFixture::create('array', 'list<array{0?: list<int>}>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{0?: list<int>}>', 'var')
                . ' from the JSON object: Field value[0] must be of type array{0?: list<int>} with between 0 and 1 items, 2 given.',
            3,
        ];
        yield 'tuple rejects fewer than its required items' => [
            '{"value":[1]}',
            CollectionDeclarationFixture::create('array', 'array{0: int, 1: string, 2?: bool, 3?: float}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'array{0: int, 1: string, 2?: bool, 3?: float}',
                    'param',
                )
                . ' from the JSON object: Field value must be of type array{int, string, 2?: bool, 3?: float} with between 2 and 4 items, 1 given.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function itemTypesAndShapes(): iterable
    {
        yield 'nullable tuple list rejects a boolean item' => [
            '{"value":[false]}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(PhpType::Null, PhpType::tuple(PhpType::Int))),
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(PhpType::Null, PhpType::tuple(PhpType::Int))),
                    'param',
                )
                . ' from the JSON object: Field value[0] must be of type array{int}, bool given.',
            3,
        ];
        yield 'tuple enum alternative rejects an unknown backing value' => [
            '{"value":[42]}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::tuple(PhpType::union(IntBackedStatus::class, PhpType::Bool)),
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::tuple(PhpType::union(IntBackedStatus::class, PhpType::Bool)),
                    'var',
                )
                . ' from the JSON object: Field value[0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus, which has no case with backing value 42.',
            3,
        ];
        yield 'tuple rejects an object with numeric member names' => [
            '{"value":{"0":1,"1":"text"}}',
            CollectionDeclarationFixture::create('array', 'array{0: int, 1: string, 2?: bool, 3?: float}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'array{0: int, 1: string, 2?: bool, 3?: float}',
                    'param',
                )
                . ' from the JSON object: Field value must be of type array{int, string, 2?: bool, 3?: float}, stdClass given.',
            3,
        ];
        yield 'optional integer tuple item rejects null' => [
            '{"value":[null]}',
            CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param')
                . ' from the JSON object: Field value[0] must be of type int, null given.',
            3,
        ];
        yield 'optional integer tuple item rejects a numeric string' => [
            '{"value":["1"]}',
            CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param')
                . ' from the JSON object: Field value[0] must be of type int, string given.',
            3,
        ];
    }
}
