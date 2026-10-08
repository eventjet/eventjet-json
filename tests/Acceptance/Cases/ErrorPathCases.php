<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class ErrorPathCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::quotedMapKeys();
        yield from self::nestedFields();
        yield from self::nestedLists();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function quotedMapKeys(): iterable
    {
        yield 'error path escapes a quote in a map key' => [
            '{"value":{"a\"b":"wrong"}}',
            CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param')
                . ' from the JSON object: Field value["a\"b"] must be of type int, string given.',
            3,
        ];
        yield 'error path quotes a dotted map key before a list index' => [
            '{"a.b":[{"firstName":1,"lastName":"Lovelace"}]}',
            JsonType::map(JsonType::array(Person::class)),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field ["a.b"][0].firstName must be of type string, int given.',
            3,
        ];
        yield 'error path escapes a newline in a map key' => [
            '{"value":{"a\n":"wrong"}}',
            CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param')
                . ' from the JSON object: Field value["a\n"] must be of type int, string given.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedFields(): iterable
    {
        yield 'error path includes nested property and map segments' => [
            '{"value":{"value":{"key":{"value":"unknown"}}}}',
            CollectionDeclarationFixture::create(
                '\\'
                . CollectionDeclarationFixture::create(
                    'array',
                    'non-empty-map<string, \\'
                    . CollectionDeclarationFixture::create(
                        '\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus',
                        '',
                        'param',
                    )
                    . '|int|null>',
                    'var',
                )
                . '|int|null',
                '',
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    '\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus',
                    '',
                    'param',
                )
                . ' from the JSON object: Field value.value[key].value uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which has no case with backing value \'unknown\'.',
            3,
        ];
        yield 'error path includes the nested enum-or-scalar field' => [
            '{"value":{"value":[]}}',
            CollectionDeclarationFixture::create(
                '\\'
                    . CollectionDeclarationFixture::create(
                        '\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|bool|null',
                        '',
                        'param',
                    ),
                '',
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    '\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|bool|null',
                    '',
                    'param',
                )
                . ' from the JSON object: Field value.value must be of type Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|bool|null, array given.',
            3,
        ];
        yield 'error path includes an invalid property inside a class-or-scalar union' => [
            '{"value":{"firstName":42,"lastName":"Lovelace"}}',
            new ClassScalarUnionField(null)::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field value.firstName must be of type string, int given.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedLists(): iterable
    {
        yield 'error path includes both list indices and the nested property' => [
            '{"value":[[{"latitude":true,"longitude":3}]]}',
            CollectionDeclarationFixture::create(
                'array',
                'list<list<\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates>>',
                'param',
            ),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Coordinates from the JSON object: Field value[0][0].latitude must be of type float, bool given.',
            3,
        ];
        yield 'error path includes both indices of an invalid scalar item' => [
            '{"value":[[true]]}',
            CollectionDeclarationFixture::create('array', 'list<list<int|null>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<list<int|null>>', 'param')
                . ' from the JSON object: Field value[0][0] must be of type int|null, bool given.',
            3,
        ];
    }
}
