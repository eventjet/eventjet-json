<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class AmbiguousUnionErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::enumAlternatives();
        yield from self::classAlternatives();
        yield from self::collectionAlternatives();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function enumAlternatives(): iterable
    {
        yield 'overlapping enum backing values are rejected even when absent' => [
            '{}',
            new OverlappingEnumUnionField(StringBackedStatus::Ready)::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField from the JSON object: Field value uses multiple backed enums Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus and Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus with overlapping backing value \'ready\'. JSON cannot identify which enum case to instantiate.',
            3,
        ];
        yield 'enum and its backing scalar cannot be distinguished' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(
                    PhpType::String,
                    StringBackedStatus::class,
                    PhpType::list(PhpType::Int),
                    PhpType::list(PhpType::String),
                )),
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(
                        PhpType::String,
                        StringBackedStatus::class,
                        PhpType::list(PhpType::Int),
                        PhpType::list(PhpType::String),
                    )),
                    'var',
                )
                . ' from the JSON object: Field value uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function classAlternatives(): iterable
    {
        yield 'self and another class cannot be distinguished' => [
            '{"value":{}}',
            new SelfClassUnionField(new Person('Ada', 'Lovelace'))::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField from the JSON object: Field value uses multiple class types: Eventjet\Json\Test\Acceptance\Fixtures\Person, Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField. JSON does not identify which class to instantiate.',
            3,
        ];
        yield 'multiple class alternatives cannot be distinguished' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(
                    Coordinates::class,
                    Person::class,
                    StringBackedStatus::class,
                    PhpType::String,
                )),
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(
                        Coordinates::class,
                        Person::class,
                        StringBackedStatus::class,
                        PhpType::String,
                    )),
                    'param',
                )
                . ' from the JSON object: Field value uses multiple class types: Eventjet\Json\Test\Acceptance\Fixtures\Coordinates, Eventjet\Json\Test\Acceptance\Fixtures\Person. JSON does not identify which class to instantiate.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function collectionAlternatives(): iterable
    {
        yield 'nested map and object alternatives have the same JSON shape' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(PhpType::arrayObject(PhpType::Int), Coordinates::class)),
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(PhpType::arrayObject(PhpType::Int), Coordinates::class)),
                    'param',
                )
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
        yield 'map and object field alternatives have the same JSON shape' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                PhpType::union(PhpType::nonEmptyArray(PhpType::Int), Coordinates::class),
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                    PhpType::union(PhpType::nonEmptyArray(PhpType::Int), Coordinates::class),
                    'param',
                )
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
        yield 'nested list alternatives have the same JSON shape' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(
                    PhpType::Int,
                    PhpType::list(PhpType::Int),
                    PhpType::list(PhpType::String),
                )),
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(
                        PhpType::Int,
                        PhpType::list(PhpType::Int),
                        PhpType::list(PhpType::String),
                    )),
                    'param',
                )
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
        yield 'tuple and list alternatives have the same JSON shape' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                PhpType::list(PhpType::union(PhpType::tuple(PhpType::Int), PhpType::list(PhpType::Int))),
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    PhpType::list(PhpType::union(PhpType::tuple(PhpType::Int), PhpType::list(PhpType::Int))),
                    'var',
                )
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
    }
}
