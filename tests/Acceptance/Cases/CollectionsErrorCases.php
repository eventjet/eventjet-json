<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields;

/** @internal */
final class CollectionsErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::declarations();
        yield from self::cachedShapes();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function declarations(): iterable
    {
        yield 'collection declaration must match the native array type' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<int>|string', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<int>|string', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'nested map declaration requires string keys' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<non-empty-array<int, int>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<non-empty-array<int, int>>', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'list declaration requires an item type' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'nullable array declaration must include null' => [
            '{}',
            CollectionDeclarationFixture::create('?array', 'list<int>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('?array', 'list<int>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function cachedShapes(): iterable
    {
        yield 'cached object-map field rejects null after valid objects' => [
            '[{},{},{},{"publicObjectMap":null}]',
            JsonType::array(new StringCollectionValidationFields()::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].publicObjectMap must be of type JSON object, null given.',
            3,
        ];
        yield 'cached list field rejects a boolean after valid objects' => [
            '[{},{},{},{"list":true}]',
            JsonType::array(StringCollectionValidationFields::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].list must be of type array, bool given.',
            3,
        ];
        yield 'cached non-empty-list field rejects null after valid objects' => [
            '[{},{},{},{"nonEmptyList":null}]',
            JsonType::array(StringCollectionValidationFields::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].nonEmptyList must be of type array, null given.',
            3,
        ];
    }
}
