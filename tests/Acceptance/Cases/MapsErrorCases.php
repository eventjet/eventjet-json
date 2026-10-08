<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\AmbiguousMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedArrayObjectMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedNonEmptyMapKeyField;

/** @internal */
final class MapsErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::declarations();
        yield from self::valuesAndShapes();
        yield from self::cachedValues();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function declarations(): iterable
    {
        yield 'ArrayObject declaration rejects integer keys even when absent' => [
            '{}',
            new UnsupportedArrayObjectMapField(new ArrayObject([0 => 'value']))::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedArrayObjectMapField from the JSON object: Field values uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            3,
        ];
        yield 'non-empty map declaration rejects integer keys even when absent' => [
            '{}',
            new UnsupportedNonEmptyMapKeyField([0 => 'value'])::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedNonEmptyMapKeyField from the JSON object: Field values uses unsupported map declaration non-empty-array. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            3,
        ];
        yield 'plain array map cannot represent an empty JSON object' => [
            '{}',
            new AmbiguousMapField([])::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AmbiguousMapField from the JSON object: Field values uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
            3,
        ];
        yield 'ArrayObject declaration rejects an unclosed generic' => [
            '{}',
            CollectionDeclarationFixture::create('\ArrayObject', 'ArrayObject<string, int', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('\ArrayObject', 'ArrayObject<string, int', 'var')
                . ' from the JSON object: Field value uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            3,
        ];
        yield 'non-empty map declaration rejects a third type argument' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'non-empty-map<string, int, bool>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'non-empty-map<string, int, bool>', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function valuesAndShapes(): iterable
    {
        yield 'boolean map rejects an integer value' => [
            '{"map":{"invalid":1}}',
            new BoolCollectionValidationFields()::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields from the JSON object: Field map[invalid] must be of type bool, int given.',
            3,
        ];
        yield 'map inside a list rejects an array' => [
            '[[]]',
            new ArrayJsonType(new MapJsonType(new ClassJsonType(EmptyObject::class))),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject from the JSON object: Field [0] must be of type JSON object, array given.',
            3,
        ];
        yield 'map property rejects a numeric member name' => [
            '{"strings":{"valid":"value"},"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true},"publicStrings":{"0":"ready"}}',
            new ScalarMapFields(['valid' => 'value'], ['valid' => 42], ['valid' => 3.25], ['valid' => true])::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields from the JSON object: Field publicStrings has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function cachedValues(): iterable
    {
        yield 'cached list validation reports its enclosing map and item index' => [
            '{"entry":[{},{},{},{"list":[1]}]}',
            new MapJsonType(new ArrayJsonType(new ClassJsonType(BoolCollectionValidationFields::class))),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields from the JSON object: Field [entry][3].list[0] must be of type bool, int given.',
            3,
        ];
    }
}
