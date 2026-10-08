<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields;

/** @internal */
final class CollectionsErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::group1();
        yield from self::group2();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group1(): iterable
    {
        yield 'warm collection Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields publicObjectMap rejects shape null after 3 objects' =>
            [
                '[{},{},{},{"publicObjectMap":null}]',
                JsonType::array(StringCollectionValidationFields::class),
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].publicObjectMap must be of type JSON object, null given.',
                3,
            ];
        yield 'warm collection Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields list rejects shape true after 3 objects' =>
            [
                '[{},{},{},{"list":true}]',
                JsonType::array(StringCollectionValidationFields::class),
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].list must be of type array, bool given.',
                3,
            ];
        yield 'warm collection Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields nonEmptyList rejects shape null after 3 objects' =>
            [
                '[{},{},{},{"nonEmptyList":null}]',
                JsonType::array(StringCollectionValidationFields::class),
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].nonEmptyList must be of type array, null given.',
                3,
            ];
        yield 'list<int>|string var {}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<int>|string', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<int>|string', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'list<non-empty-array<int, int>> param {}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<non-empty-array<int, int>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<non-empty-array<int, int>>', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'list<> var {}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group2(): iterable
    {
        yield 'missing nullable collection declaration var {}' => [
            '{}',
            CollectionDeclarationFixture::create('?array', 'list<int>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('?array', 'list<int>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }
}
