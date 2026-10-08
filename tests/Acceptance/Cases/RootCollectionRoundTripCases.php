<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class RootCollectionRoundTripCases
{
    /**
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::group1();
        yield from self::group2();
    }

    /**
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group1(): iterable
    {
        yield 'cached public fields keep values and defaults independent' => [
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
            null,
            JsonType::array(ConstructorlessPublicProperties::class),
        ];
        yield 'people depth 2' => [
            [
                [],
                [new Person('Ada', 'Lovelace'), new Person('Grace', 'Hopper')],
                [new Person('Grace', 'Hopper'), new Person('Ada', 'Lovelace')],
            ],
            null,
            JsonType::array(JsonType::array(Person::class)),
        ];
        yield 'empty objects depth 2' => [
            [[], [new EmptyObject(), new EmptyObject()], [new EmptyObject(), new EmptyObject()]],
            null,
            JsonType::array(JsonType::array(EmptyObject::class)),
        ];
        yield 'empty root array depth 1' => [[], null, JsonType::array(EmptyObject::class)];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus length 2 depth 1' => [
            [StringBackedStatus::Ready, StringBackedStatus::Pending],
            null,
            JsonType::array(StringBackedStatus::class),
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject keys' => [
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
            null,
            JsonType::map(EmptyObject::class),
        ];
    }

    /**
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group2(): iterable
    {
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject empty' => [
            new ArrayObject([]),
            null,
            JsonType::map(EmptyObject::class),
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject map of maps' => [
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
            null,
            JsonType::map(JsonType::map(EmptyObject::class)),
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject map of lists' => [
            new ArrayObject(['empty' => [], 'values' => [new EmptyObject()]]),
            null,
            JsonType::map(JsonType::array(EmptyObject::class)),
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus keys' => [
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
            null,
            JsonType::map(StringBackedStatus::class),
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus keys' => [
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
            null,
            JsonType::map(IntBackedStatus::class),
        ];
    }
}
