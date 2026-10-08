<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

/** @internal */
final class TupleDeclarationErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'tuple declaration cannot require an item after an optional item' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{0?: int, string}', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{0?: int, string}', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'tuple integer range rejects reversed bounds' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{int<max, min>}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{int<max, min>}', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'tuple integer range rejects a third bound' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{int<min, max, 0>}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{int<min, max, 0>}', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'tuple declaration rejects an unsupported nested map' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<array{list<array<string, int>>}>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{list<array<string, int>>}>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'tuple indices must start at zero' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'array{1: int}', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{1: int}', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'tuple union rejects a generic null declaration' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<array{0: int}|null<string>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<array{0: int}|null<string>>', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }
}
