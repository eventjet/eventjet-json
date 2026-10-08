<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty;

/** @internal */
final class UnionValueErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'tuple-or-string field rejects a boolean' => [
            '{"value":false}',
            CollectionDeclarationFixture::create('array|string', 'array{0: int, 1?: string}|string', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|string', 'array{0: int, 1?: string}|string', 'param')
                . ' from the JSON object: Field value must be of type array{0: int, 1?: string}|string, bool given.',
            3,
        ];
        yield 'disjoint enum union rejects an unknown backing value' => [
            '{"value":"unknown"}',
            DisjointStringBackedEnumUnionField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField from the JSON object: Field value uses backed enum union Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which has no case with backing value \'unknown\'.',
            3,
        ];
        yield 'non-empty-list-or-string field rejects an empty list' => [
            '{"value":[]}',
            CollectionDeclarationFixture::create('array|string', 'non-empty-list<int>|string', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|string', 'non-empty-list<int>|string', 'var')
                . ' from the JSON object: Field value must be of type non-empty-list<int>, empty list given.',
            3,
        ];
        yield 'scalar union property rejects an object' => [
            '{"value":{}}',
            UnionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty from the JSON object: Field value must be of type string|int, stdClass given.',
            3,
        ];
    }
}
