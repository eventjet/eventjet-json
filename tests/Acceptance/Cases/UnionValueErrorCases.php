<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty;

/** @internal */
final class UnionValueErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from NativeBooleanUnionCases::errors();
        $selfUnion = CollectionDeclarationFixture::create('self|int', '', 'param');
        yield 'native union names self by the declaring class' => [
            '{"value":"x"}',
            $selfUnion,
            'Could not create '
                . $selfUnion
                . ' from the JSON object: Field value must be of type '
                . $selfUnion
                . '|int, string given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield from self::unloadedClassMembers();
        yield 'tuple-or-string field rejects a boolean' => [
            '{"value":false}',
            CollectionDeclarationFixture::create('array|string', 'array{0: int, 1?: string}|string', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|string', 'array{0: int, 1?: string}|string', 'param')
                . ' from the JSON object: Field value must be of type array{0: int, 1?: string}|string, bool given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'disjoint enum union rejects an unknown backing value' => [
            '{"value":"unknown"}',
            DisjointStringBackedEnumUnionField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField from the JSON object: Field value uses backed enum union Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which has no case with backing value \'unknown\'.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'non-empty-list-or-string field rejects an empty list' => [
            '{"value":[]}',
            CollectionDeclarationFixture::create('array|string', 'non-empty-list<int>|string', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|string', 'non-empty-list<int>|string', 'var')
                . ' from the JSON object: Field value must be of type non-empty-list<int>, empty list given.',
            DecodeErrorKind::InvalidValue,
        ];
        $repeatedNullable = CollectionDeclarationFixture::create('array|null', 'list<int>|list<int>|null', 'param');
        yield 'repeated nullable list member reports the list type like a single one' => [
            '{"value":"x"}',
            $repeatedNullable,
            'Could not create '
                . $repeatedNullable
                . ' from the JSON object: Field value must be of type list<int>, string given.',
            DecodeErrorKind::InvalidValue,
        ];
        $nullFirst = CollectionDeclarationFixture::create(
            'array',
            PhpType::list(PhpType::union(PhpType::Null, Coordinates::class)),
            'param',
        );
        yield 'nullable object list reports null after the class like a single field' => [
            '{"value":[1]}',
            $nullFirst,
            'Could not create '
                . $nullFirst
                . ' from the JSON object: Field value[0] must be of type Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|null, int given.',
            DecodeErrorKind::InvalidValue,
        ];
        $withoutValues = CollectionDeclarationFixture::create(
            'array',
            PhpType::list(PhpType::union(NonBackedStatus::class, PhpType::Int)),
            'param',
        );
        yield 'union reports members without JSON values in its type' => [
            '{"value":["ready"]}',
            $withoutValues,
            'Could not create '
                . $withoutValues
                . ' from the JSON object: Field value[0] must be of type Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|int, string given.',
            DecodeErrorKind::InvalidValue,
        ];
        $nullableResource = CollectionDeclarationFixture::create('?\resource', '', 'var');
        yield 'nullable native class named resource reads as a resource, which accepts only null' => [
            '{"value":{}}',
            $nullableResource,
            'Could not create '
                . $nullableResource
                . ' from the JSON object: Field value must be of type resource|null, stdClass given.',
            DecodeErrorKind::InvalidValue,
        ];
        $repeated = CollectionDeclarationFixture::create(
            'int|string',
            '/** @param int|string $value Version 2 */',
            'param',
        );
        yield 'PHPDoc that repeats the native union leaves its type in errors' => [
            '{"value":true}',
            $repeated,
            'Could not create '
                . $repeated
                . ' from the JSON object: Field value must be of type string|int, bool given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'scalar union property rejects an object' => [
            '{"value":{}}',
            UnionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty from the JSON object: Field value must be of type string|int, stdClass given.',
            DecodeErrorKind::InvalidValue,
        ];
    }

    /**
     * An unloaded class in a union rejects JSON objects with the union type, and leaves other values to the other
     * members.
     *
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    private static function unloadedClassMembers(): iterable
    {
        $class = CollectionNameSource::load(
            'UnloadedClassUnionFields',
            'final class UnloadedClassUnionFields { '
            . 'public function __construct(public UnloadedUnionClass|int $value, public int $count) {} }',
        );
        yield 'union with an unloaded class rejects an object' => [
            '{"value":{},"count":1}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value must be of type UnloadedUnionClass|int, stdClass given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'union with an unloaded class accepts an integer' => [
            '{"value":1,"count":"one"}',
            $class,
            'Could not create ' . $class . ' from the JSON object: Field count must be of type int, string given.',
            DecodeErrorKind::InvalidValue,
        ];
    }
}
