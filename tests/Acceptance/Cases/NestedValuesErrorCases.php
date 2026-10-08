<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;

/** @internal */
final class NestedValuesErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'nested nullable list rejects an integer' => [
            '{"value":[42]}',
            \Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(PhpType::list(PhpType::Int), PhpType::Null)),
                'param',
            ),
            'Could not create '
                . \Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(PhpType::list(PhpType::Int), PhpType::Null)),
                    'param',
                )
                . ' from the JSON object: Field value[0] must be of type list<int>, int given.',
            3,
        ];
        yield 'nested list field rejects an object' => [
            '{"value":{}}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(PhpType::list(PhpType::Int), PhpType::Null)),
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(PhpType::list(PhpType::Int), PhpType::Null)),
                    'param',
                )
                . ' from the JSON object: Field value must be of type list<list<int>|null>, stdClass given.',
            3,
        ];
        yield 'nested list of maps rejects an object' => [
            '[{}]',
            JsonType::array(JsonType::array(JsonType::map(EmptyObject::class))),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject from the JSON object: Field [0] must be of type list<ArrayObject<string, Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject>>, stdClass given.',
            3,
        ];
        yield 'nullable nested object rejects a scalar value' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"address":{"city":"London","coordinates":{"latitude":51.5,"longitude":-0.1}},"alternate":"Charles"}',
            NestedObjectFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields from the JSON object: Field alternate must be of type Eventjet\Json\Test\Acceptance\Fixtures\Person|null, string given.',
            3,
        ];
        yield 'nested non-empty map rejects an empty object' => [
            '{"value":[{}]}',
            CollectionDeclarationFixture::create('array', PhpType::list(PhpType::nonEmptyArray(PhpType::Int)), 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::nonEmptyArray(PhpType::Int)),
                    'var',
                )
                . ' from the JSON object: Field value[0] uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            3,
        ];
    }
}
