<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;
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
        yield from self::scalarItems();
        yield from self::objectItems();
    }

    /** @return iterable<string, array{string, JsonType<list<Person>>, string, int}> */
    private static function objectItems(): iterable
    {
        foreach ([['null', 'null'], ['[]', 'array'], ['false', 'bool'], ['1', 'int'], ['"wrong"', 'string']] as [
            $json,
            $type,
        ]) {
            foreach ([0, 1] as $index) {
                $prefix = $index === 0 ? '' : '{"firstName":"Ada","lastName":"Lovelace"},';
                yield 'object list rejects ' . $type . ' at index ' . $index => [
                    '[' . $prefix . $json . ']',
                    JsonType::array(Person::class),
                    'Could not create '
                        . Person::class
                        . ' from the JSON object: Field ['
                        . $index
                        . '] must be of type '
                        . Person::class
                        . ', '
                        . $type
                        . ' given.',
                    DecodeErrorKind::InvalidValue->value,
                ];
            }
        }
        yield 'object list validates fields after warming the item plan' => [
            '[{"firstName":"Ada","lastName":"Lovelace"},{"firstName":42,"lastName":"Hopper"}]',
            JsonType::array(Person::class),
            'Could not create '
                . Person::class
                . ' from the JSON object: Field [1].firstName must be of type string, int given.',
            DecodeErrorKind::InvalidValue->value,
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws \RuntimeException
     */
    private static function scalarItems(): iterable
    {
        yield 'object map declared in PHPDoc rejects null' => [
            '{"value":{"a":null}}',
            CollectionDeclarationFixture::create('array', PhpType::nonEmptyArray(Coordinates::class), 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', PhpType::nonEmptyArray(Coordinates::class), 'param')
                . ' from the JSON object: Field value[a] must be of type Eventjet\Json\Test\Acceptance\Fixtures\Coordinates, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        $lowercaseItems = CollectionDeclarationFixture::create(
            'array',
            'list<\\eventjet\\json\\test\\acceptance\\fixtures\\coordinates>',
            'param',
        );
        yield 'object list reports the declared spelling of its item class' => [
            '{"value":[null]}',
            $lowercaseItems,
            'Could not create '
                . $lowercaseItems
                . ' from the JSON object: Field value[0] must be of type Eventjet\Json\Test\Acceptance\Fixtures\Coordinates, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'object list declared in PHPDoc rejects null' => [
            '{"value":[null]}',
            CollectionDeclarationFixture::create('array', PhpType::list(Coordinates::class), 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', PhpType::list(Coordinates::class), 'param')
                . ' from the JSON object: Field value[0] must be of type Eventjet\Json\Test\Acceptance\Fixtures\Coordinates, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        foreach ([
            ['strings',      'string', '"valid"'],
            ['stringsExtra', 'int',    '1'],
            ['booleans',     'bool',   'true'],
            ['floats',       'float',  '1.5'],
        ] as [$field, $type, $valid]) {
            yield $type . ' list validates items after the first' => [
                '{"' . $field . '":[' . $valid . ',null]}',
                ScalarListFields::class,
                'Could not create '
                    . ScalarListFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . '[1] must be of type '
                    . $type
                    . ', null given.',
                DecodeErrorKind::InvalidValue->value,
            ];
        }
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
            CollectionDeclarationFixture::create(
                'array',
                PhpType::union(PhpType::list(PhpType::Int), PhpType::String),
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::union(PhpType::list(PhpType::Int), PhpType::String),
                    'var',
                )
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var, @phpstan-var, @psalm-var, or @phan-var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'nested map declaration requires string keys' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<non-empty-array<int, int>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<non-empty-array<int, int>>', 'param')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param, @phpstan-param, @psalm-param, or @phan-param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'list items cannot be non-backed enums' => [
            '{}',
            CollectionDeclarationFixture::create('array', PhpType::list(NonBackedStatus::class), 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', PhpType::list(NonBackedStatus::class), 'param')
                . ' from the JSON object: Field value uses non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'list declaration requires an item type' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var, @phpstan-var, @psalm-var, or @phan-var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        $nullableList = CollectionDeclarationFixture::create(
            '?array',
            PhpType::union(PhpType::list(PhpType::Int), PhpType::Null),
            'var',
        );
        yield 'nullable list field rejects a string' => [
            '{"value":"text"}',
            $nullableList,
            'Could not create '
                . $nullableList
                . ' from the JSON object: Field value must be of type list<int>, string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        $bareMapItems = CollectionDeclarationFixture::create('array', 'list<\\ArrayObject>', 'var');
        yield 'ArrayObject list items require type arguments' => [
            '{}',
            $bareMapItems,
            'Could not create '
                . $bareMapItems
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var, @phpstan-var, @psalm-var, or @phan-var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        $nullableGeneric = CollectionDeclarationFixture::create('?array', 'array<string, int>', 'param');
        yield 'nullable array field rejects a generic array declaration' => [
            '{}',
            $nullableGeneric,
            'Could not create '
                . $nullableGeneric
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param, @phpstan-param, @psalm-param, or @phan-param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'nullable array declaration must include null' => [
            '{}',
            CollectionDeclarationFixture::create('?array', 'list<int>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('?array', 'list<int>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var, @phpstan-var, @psalm-var, or @phan-var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            DecodeErrorKind::UnsupportedDeclaration->value,
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
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'cached list field rejects a boolean after valid objects' => [
            '[{},{},{},{"list":true}]',
            JsonType::array(StringCollectionValidationFields::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].list must be of type array, bool given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'cached non-empty-list field rejects null after valid objects' => [
            '[{},{},{},{"nonEmptyList":null}]',
            JsonType::array(StringCollectionValidationFields::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields from the JSON object: Field [3].nonEmptyList must be of type array, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
    }
}
