<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class RootCollectionRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>|object, callable(): JsonType<list<mixed>|object>}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'root list of mapped references' => [
            [
                new MappedReference('#/first'),
                new MappedReference('#/second'),
            ],
            static fn(): JsonType => JsonType::array(MappedReference::class),
        ];
        yield 'root map of mapped references' => [
            new ArrayObject(['first' => new MappedReference('#/first')]),
            static fn(): JsonType => JsonType::map(MappedReference::class),
        ];
        yield from self::objectLists();
        yield from self::enumLists();
        yield from self::nestedCollections();
        yield from self::maps();
    }

    /**
     * @return iterable<string, array{list<mixed>|object, callable(): JsonType<list<mixed>|object>}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function objectLists(): iterable
    {
        yield 'list objects keep cached values and defaults independent' => [
            [
                (static function (): object {
                    $object = new ConstructorlessPublicProperties();
                    $object->string = 'first';
                    $object->integer = 42;
                    $object->float = 0.0;
                    $object->boolean = true;
                    $object->nullable = 'present';
                    return $object;
                })(),
                (static function (): object {
                    $object = new ConstructorlessPublicProperties();
                    $object->string = 'second';
                    $object->integer = 0;
                    $object->float = 1.5;
                    $object->boolean = false;
                    $object->nullable = null;
                    return $object;
                })(),
                (static function (): object {
                    $object = new ConstructorlessPublicProperties();
                    $object->string = '';
                    $object->integer = 0;
                    $object->float = 0.0;
                    $object->boolean = false;
                    $object->nullable = null;
                    return $object;
                })(),
            ],
            static fn(): JsonType => JsonType::array(ConstructorlessPublicProperties::class),
        ];
        yield 'nested object lists preserve empty lists and item order' => [
            [
                [],
                [new Person('Ada', 'Lovelace'), new Person('Grace', 'Hopper')],
                [new Person('Grace', 'Hopper'), new Person('Ada', 'Lovelace')],
            ],
            static fn(): JsonType => JsonType::array(JsonType::array(Person::class)),
        ];
        yield 'nested empty-object lists preserve array and object shapes' => [
            [[], [new EmptyObject(), new EmptyObject()], [new EmptyObject(), new EmptyObject()]],
            static fn(): JsonType => JsonType::array(JsonType::array(EmptyObject::class)),
        ];
        yield 'empty root list preserves array shape' => [
            [],
            static fn(): JsonType => JsonType::array(EmptyObject::class),
        ];
    }

    /**
     * @return iterable<string, array{list<mixed>|object, callable(): JsonType<list<mixed>|object>}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function enumLists(): iterable
    {
        yield 'root list restores string-backed enums' => [
            [StringBackedStatus::Ready, StringBackedStatus::Pending],
            static fn(): JsonType => JsonType::array(StringBackedStatus::class),
        ];
        yield 'root list restores integer-backed enums' => [
            [IntBackedStatus::Ready],
            static fn(): JsonType => JsonType::array(IntBackedStatus::class),
        ];
    }

    /**
     * @return iterable<string, array{list<mixed>|object, callable(): JsonType<list<mixed>|object>}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedCollections(): iterable
    {
        yield 'root list restores maps of objects' => [
            [new ArrayObject(['author' => new Person('Ada', 'Lovelace')])],
            static fn(): JsonType => JsonType::array(JsonType::map(Person::class)),
        ];
        yield 'root list preserves nested list, map, and integer alternatives' => [
            [CollectionDeclarationFixture::object('array', 'list<list<int>|non-empty-map<string, bool>|int>', 'param', [
                [],
                [1],
                ['01' => false],
                0,
            ])],
            /** @throws \RuntimeException */ static fn(): JsonType => JsonType::array(CollectionDeclarationFixture::create(
                'array',
                'list<list<int>|non-empty-map<string, bool>|int>',
                'param',
            )),
        ];
        yield 'nested object maps preserve empty maps and special keys' => [
            new ArrayObject([
                'first' => new ArrayObject([]),
                'next' => new ArrayObject([
                    '01' => new EmptyObject(),
                    '1e0' => new EmptyObject(),
                    '-01' => new EmptyObject(),
                    'a.b' => new EmptyObject(),
                    '' => new EmptyObject(),
                    'a"b' => new EmptyObject(),
                    'line
key' => new EmptyObject(),
                    'é' => new EmptyObject(),
                    'primary' => new EmptyObject(),
                ]),
            ]),
            static fn(): JsonType => JsonType::map(JsonType::map(EmptyObject::class)),
        ];
        yield 'root map preserves empty and non-empty lists' => [
            new ArrayObject(['empty' => [], 'values' => [new EmptyObject()]]),
            static fn(): JsonType => JsonType::map(JsonType::array(EmptyObject::class)),
        ];
    }

    /**
     * @return iterable<string, array{list<mixed>|object, callable(): JsonType<list<mixed>|object>}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function maps(): iterable
    {
        yield 'object map preserves special string keys' => [
            new ArrayObject([
                '01' => new EmptyObject(),
                '1e0' => new EmptyObject(),
                '-01' => new EmptyObject(),
                'a.b' => new EmptyObject(),
                '' => new EmptyObject(),
                'a"b' => new EmptyObject(),
                'line
key' => new EmptyObject(),
                'é' => new EmptyObject(),
                'primary' => new EmptyObject(),
            ]),
            static fn(): JsonType => JsonType::map(EmptyObject::class),
        ];
        yield 'empty root map preserves object shape' => [
            new ArrayObject([]),
            static fn(): JsonType => JsonType::map(EmptyObject::class),
        ];
        yield 'string-backed enum map preserves special keys' => [
            new ArrayObject([
                '01' => StringBackedStatus::Ready,
                '1e0' => StringBackedStatus::Pending,
                '-01' => StringBackedStatus::Ready,
                'a.b' => StringBackedStatus::Pending,
                '' => StringBackedStatus::Ready,
                'a"b' => StringBackedStatus::Pending,
                'line
key' => StringBackedStatus::Ready,
                'é' => StringBackedStatus::Pending,
                'primary' => StringBackedStatus::Ready,
            ]),
            static fn(): JsonType => JsonType::map(StringBackedStatus::class),
        ];
        yield 'integer-backed enum map preserves special keys' => [
            new ArrayObject([
                '01' => IntBackedStatus::Ready,
                '1e0' => IntBackedStatus::Pending,
                '-01' => IntBackedStatus::Ready,
                'a.b' => IntBackedStatus::Pending,
                '' => IntBackedStatus::Ready,
                'a"b' => IntBackedStatus::Pending,
                'line
key' => IntBackedStatus::Ready,
                'é' => IntBackedStatus::Pending,
                'primary' => IntBackedStatus::Ready,
            ]),
            static fn(): JsonType => JsonType::map(IntBackedStatus::class),
        ];
    }
}
