<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
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
                    3,
                ];
            }
        }
        yield 'object list validates fields after warming the item plan' => [
            '[{"firstName":"Ada","lastName":"Lovelace"},{"firstName":42,"lastName":"Hopper"}]',
            JsonType::array(Person::class),
            'Could not create '
                . Person::class
                . ' from the JSON object: Field [1].firstName must be of type string, int given.',
            3,
        ];
    }

    /** @return iterable<string, array{string, class-string, string, int}> */
    private static function scalarItems(): iterable
    {
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
                3,
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
