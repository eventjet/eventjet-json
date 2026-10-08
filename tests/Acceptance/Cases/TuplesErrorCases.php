<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\TupleFields;

/** @internal */
final class TuplesErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::tuples1();
        yield from self::tuples2();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function tuples1(): iterable
    {
        yield 'tuple empty rejects nonempty' => [
            '{"value":[1,"text",3,true,"ready",1,{"firstName":"Ada","lastName":"Lovelace","middleName":null,"age":null}],"empty":[0]}',
            TupleFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\TupleFields from the JSON object: Field empty must be of type array{} with exactly 0 items, 1 given.',
            3,
        ];
        yield 'nested tuple invalid value 8param' => [
            '{"value":[false]}',
            CollectionDeclarationFixture::create('array', 'list<null|array{int}>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<null|array{int}>', 'param')
                . ' from the JSON object: Field value[0] must be of type array{int}, bool given.',
            3,
        ];
        yield 'array{0?: int, string} var {}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{0?: int, string}', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{0?: int, string}', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'parser malformed or unsupported array{int<max, min>} param' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{int<max, min>}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{int<max, min>}', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'parser malformed or unsupported array{int<min, max, 0>} param' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{int<min, max, 0>}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{int<min, max, 0>}', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'collection enum union array{\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|float}var' => [
            '{"value":[42]}',
            CollectionDeclarationFixture::create(
                'array',
                'array{\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|float}',
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'array{\Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus|float}',
                    'var',
                )
                . ' from the JSON object: Field value[0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus, which has no case with backing value 42.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function tuples2(): iterable
    {
        yield 'invalid nested shape declaration list<array{list<array<string, int>>}>var{}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<array{list<array<string, int>>}>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{list<array<string, int>>}>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'nested tuple invalid value 5var' => [
            '{"value":[[[],[]]]}',
            CollectionDeclarationFixture::create('array', 'list<array{0?: list<int>}>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{0?: list<int>}>', 'var')
                . ' from the JSON object: Field value[0] must be of type array{0?: list<int>} with between 0 and 1 items, 2 given.',
            3,
        ];
        yield 'invalid nested shape declaration list<array{int}|list<int>>var{}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<array{int}|list<int>>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{int}|list<int>>', 'var')
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
        yield 'array{1: int} var {}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{1: int}', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{1: int}', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'invalid nested shape declaration list<array{0: int}|null<string>>param{}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<array{0: int}|null<string>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{0: int}|null<string>>', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }
}
