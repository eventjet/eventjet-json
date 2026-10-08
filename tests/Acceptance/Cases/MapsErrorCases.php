<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\AmbiguousMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedArrayObjectMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedNonEmptyMapKeyField;

/** @internal */
final class MapsErrorCases
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
        yield 'ArrayObject map with integer key type, member absent' => [
            '{}',
            UnsupportedArrayObjectMapField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedArrayObjectMapField from the JSON object: Field values uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            3,
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields map rejects {"invalid":1}' => [
            '{"map":{"invalid":1}}',
            BoolCollectionValidationFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields from the JSON object: Field map[invalid] must be of type bool, int given.',
            3,
        ];
        yield 'non-empty array map with integer key type, member absent' => [
            '{}',
            UnsupportedNonEmptyMapKeyField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedNonEmptyMapKeyField from the JSON object: Field values uses unsupported map declaration non-empty-array. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            3,
        ];
        yield 'ambiguous array map, member absent' => [
            '{}',
            AmbiguousMapField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AmbiguousMapField from the JSON object: Field values uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
            3,
        ];
        yield 'invalid ArrayObject ArrayObject<string, int var {}' => [
            '{}',
            CollectionDeclarationFixture::create('\ArrayObject', 'ArrayObject<string, int', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('\ArrayObject', 'ArrayObject<string, int', 'var')
                . ' from the JSON object: Field value uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            3,
        ];
        yield 'map inside list rejects array' => [
            '[[]]',
            new ArrayJsonType(new MapJsonType(new ClassJsonType(EmptyObject::class))),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject from the JSON object: Field [0] must be of type JSON object, array given.',
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
        yield 'inside map warm collection Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields list rejects [1] after 3 objects' =>
            [
                '{"entry":[{},{},{},{"list":[1]}]}',
                new MapJsonType(new ArrayJsonType(new ClassJsonType(BoolCollectionValidationFields::class))),
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields from the JSON object: Field [entry][3].list[0] must be of type bool, int given.',
                3,
            ];
        yield 'non-empty-map<string, int, bool> var {}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'non-empty-map<string, int, bool>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'non-empty-map<string, int, bool>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }
}
