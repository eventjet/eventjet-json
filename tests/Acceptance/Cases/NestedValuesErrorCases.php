<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class NestedValuesErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::nestedValues1();
        yield from self::nestedValues2();
        yield 'non-final nested class is rejected' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"value":{},"label":"value"}',
            ParentClassField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField from the JSON object: Field value uses non-final class Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase. Values may be subclasses, whose runtime class JSON does not identify.',
            3,
        ];
        yield 'nested conversion list<int>|null42param' => [
            '{"value":[42]}',
            \Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture::create(
                'array',
                'list<list<int>|null>',
                'param',
            ),
            'Could not create '
                . \Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture::create(
                    'array',
                    'list<list<int>|null>',
                    'param',
                )
                . ' from the JSON object: Field value[0] must be of type list<int>, int given.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedValues1(): iterable
    {
        yield 'conversion path unknown enum valueparamvarnon-empty-map2' => [
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
        yield 'nested declaration in root shape error list<list<int>|null>param' => [
            '{"value":{}}',
            CollectionDeclarationFixture::create('array', 'list<list<int>|null>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<list<int>|null>', 'param')
                . ' from the JSON object: Field value must be of type list<list<int>|null>, stdClass given.',
            3,
        ];
        yield 'nested list of maps rejects object' => [
            '[{}]',
            JsonType::array(JsonType::array(JsonType::map(EmptyObject::class))),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject from the JSON object: Field [0] must be of type list<ArrayObject<string, Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject>>, stdClass given.',
            3,
        ];
        yield 'quoted map key and list field path' => [
            '{"a.b":[{"firstName":1,"lastName":"Lovelace"}]}',
            JsonType::map(JsonType::array(Person::class)),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field ["a.b"][0].firstName must be of type string, int given.',
            3,
        ];
        yield 'nested conversion list<\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates>[{"latitude":true,"longitude":3}]param' =>
            [
                '{"value":[[{"latitude":true,"longitude":3}]]}',
                CollectionDeclarationFixture::create(
                    'array',
                    'list<list<\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates>>',
                    'param',
                ),
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Coordinates from the JSON object: Field value[0][0].latitude must be of type float, bool given.',
                3,
            ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedValues2(): iterable
    {
        yield 'nullable nested object rejects a scalar value' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"address":{"city":"London","coordinates":{"latitude":51.5,"longitude":-0.1}},"alternate":"Charles"}',
            NestedObjectFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields from the JSON object: Field alternate must be of type Eventjet\Json\Test\Acceptance\Fixtures\Person|null, string given.',
            3,
        ];
        yield 'nested conversion list<int|null>[true]param' => [
            '{"value":[[true]]}',
            CollectionDeclarationFixture::create('array', 'list<list<int|null>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<list<int|null>>', 'param')
                . ' from the JSON object: Field value[0][0] must be of type int|null, bool given.',
            3,
        ];
        yield 'quoted map path "a\n"param' => [
            '{"value":{"a\n":"wrong"}}',
            CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param')
                . ' from the JSON object: Field value["a\n"] must be of type int, string given.',
            3,
        ];
        yield 'nested conversion non-empty-array<string, int>{}var' => [
            '{"value":[{}]}',
            CollectionDeclarationFixture::create('array', 'list<non-empty-array<string, int>>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<non-empty-array<string, int>>', 'var')
                . ' from the JSON object: Field value[0] uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            3,
        ];
    }
}
