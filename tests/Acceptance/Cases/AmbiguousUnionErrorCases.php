<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\CaselessIntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class AmbiguousUnionErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::enumAlternatives();
        yield from self::classAlternatives();
        yield from self::collectionAlternatives();
        yield from self::literalAlternatives();
        yield from self::conflictOrder();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function enumAlternatives(): iterable
    {
        $enumFloat = PhpType::union(IntBackedStatus::class, PhpType::Float);
        foreach (['param', 'var'] as $tag) {
            foreach ([
                [(string) $enumFloat, '', 'value'],
                ['array', PhpType::list($enumFloat), 'value'],
                ['array', PhpType::tuple($enumFloat), 'value[0]'],
                [ArrayObject::class, PhpType::arrayObject($enumFloat), 'value'],
                ['array', PhpType::list(PhpType::list($enumFloat)), 'value'],
            ] as [$native, $declaration, $path]) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                foreach (['{}', '{"value":1}', '{"value":2}'] as $json) {
                    yield $tag
                        . ' rejects enum/float ambiguity in '
                        . $native
                        . ' '
                        . (string) $declaration
                        . ' '
                        . $json => [
                        $json,
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field '
                            . $path
                            . ' uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
                        DecodeErrorKind::UnsupportedDeclaration,
                    ];
                }
            }
        }
        yield 'overlapping enum backing values are rejected even when absent' => [
            '{}',
            new OverlappingEnumUnionField(StringBackedStatus::Ready)::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField from the JSON object: Field value uses multiple backed enums Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus and Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus with overlapping backing value \'ready\'. JSON cannot identify which enum case to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
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
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function classAlternatives(): iterable
    {
        yield 'self and another class cannot be distinguished' => [
            '{"value":{}}',
            new SelfClassUnionField(new Person('Ada', 'Lovelace'))::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField from the JSON object: Field value uses multiple class types: Eventjet\Json\Test\Acceptance\Fixtures\Person, Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField. JSON does not identify which class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'an unloaded class and another class cannot be distinguished' => [
            '{}',
            CollectionDeclarationFixture::create('UnloadedUnionClass|\\' . Person::class, '', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('UnloadedUnionClass|\\' . Person::class, '', 'param')
                . ' from the JSON object: Field value uses multiple class types: Eventjet\Json\Test\Acceptance\Fixtures\Person, UnloadedUnionClass. JSON does not identify which class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
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
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
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
            DecodeErrorKind::UnsupportedDeclaration,
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
            DecodeErrorKind::UnsupportedDeclaration,
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
            DecodeErrorKind::UnsupportedDeclaration,
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
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * Literals that JSON cannot tell apart from another member, because both accept a JSON value and restore different
     * PHP values from it.
     *
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     */
    private static function literalAlternatives(): iterable
    {
        $int = '\\' . IntBackedStatus::class;
        foreach ([
            'list<' . $int . '::Ready|float>',
            'list<-0.0|0>',
            'list<-0.0|' . $int . '>',
            'list<' . $int . '::Pending|-0.0>',
            "list<'ready'|\\" . StringBackedStatus::class . '|string>',
        ] as $declaration) {
            yield 'literal union ' . $declaration . ' is ambiguous' => self::declarationError(
                $declaration,
                'has overlapping literal union members. JSON cannot identify which PHP type to restore.',
            );
        }
    }

    /**
     * Unions whose members conflict in several ways report one of the conflicts.
     *
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     */
    private static function conflictOrder(): iterable
    {
        $int = '\\' . IntBackedStatus::class;
        $string = '\\' . StringBackedStatus::class;
        $overlapping = '\\' . OverlappingStringBackedStatus::class;
        $outcome = '\\' . StringBackedOutcome::class;
        foreach ([
            [
                'list<' . $int . '|float|\\' . Coordinates::class . '|\\' . Person::class . '>',
                'uses multiple class types: '
                    . Coordinates::class
                    . ', '
                    . Person::class
                    . '. JSON does not identify which class to instantiate.',
            ],
            [
                'list<' . $int . '|int|float>',
                'uses backed enum '
                    . IntBackedStatus::class
                    . ' together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
            ],
            [
                'list<\\' . CaselessIntBackedStatus::class . '|float>',
                'uses backed enum '
                    . CaselessIntBackedStatus::class
                    . ' together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
            ],
            [
                'list<' . $string . '|' . $overlapping . '|string>',
                'uses backed enum '
                    . StringBackedStatus::class
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            ],
            [
                'list<' . $string . '|' . $outcome . '|string>',
                'uses backed enum '
                    . StringBackedStatus::class
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            ],
            [
                'list<' . $string . '|int|string>',
                'uses backed enum '
                    . StringBackedStatus::class
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            ],
            [
                'list<' . $outcome . '|\\' . OverlappingStringBackedOutcome::class . '|list<int>|list<string>>',
                'uses multiple backed enums '
                    . OverlappingStringBackedOutcome::class
                    . ' and '
                    . StringBackedOutcome::class
                    . " with overlapping backing value 'failed'. JSON cannot identify which enum case to instantiate.",
            ],
        ] as [$declaration, $error]) {
            yield 'union ' . $declaration . ' reports one conflict' => self::declarationError($declaration, $error);
        }
    }

    /** @return array{string, class-string, string, DecodeErrorKind} */
    private static function declarationError(string $declaration, string $error): array
    {
        $class = CollectionDeclarationFixture::create('array', $declaration, 'param');
        return [
            '{}',
            $class,
            'Could not create ' . $class . ' from the JSON object: Field value ' . $error,
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }
}
