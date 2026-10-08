<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ArrayScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty;

/** @internal */
final class UnionsErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::unions1();
        yield from self::unions2();
        yield from self::unions3();
        yield from self::unions4();
        yield from self::group5();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function unions1(): iterable
    {
        yield 'collection union regression 0param{"value":false}' => [
            '{"value":false}',
            CollectionDeclarationFixture::create('array|string', 'array{0: int, 1?: string}|string', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|string', 'array{0: int, 1?: string}|string', 'param')
                . ' from the JSON object: Field value must be of type array{0: int, 1?: string}|string, bool given.',
            3,
        ];
        yield 'nested ambiguous union ArrayObject<string, int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinatesparam{}' =>
            [
                '{}',
                CollectionDeclarationFixture::create(
                    'array',
                    'list<ArrayObject<string, int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates>',
                    'param',
                ),
                'Could not create '
                    . CollectionDeclarationFixture::create(
                        'array',
                        'list<ArrayObject<string, int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates>',
                        'param',
                    )
                    . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                3,
            ];
        yield 'multiple string-backed enum union rejects an unknown string' => [
            '{"value":"unknown"}',
            DisjointStringBackedEnumUnionField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField from the JSON object: Field value uses backed enum union Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome|Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which has no case with backing value \'unknown\'.',
            3,
        ];
        yield 'overlapping enum union rejects field absent' => [
            '{}',
            OverlappingEnumUnionField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField from the JSON object: Field value uses multiple backed enums Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus and Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus with overlapping backing value \'ready\'. JSON cannot identify which enum case to instantiate.',
            3,
        ];
        yield 'conversion path enum scalar unionparamvarobject1' => [
            '{"value":{"value":[]}}',
            CollectionDeclarationFixture::create(
                '\\'
                    . CollectionDeclarationFixture::create(
                        '\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|bool|null',
                        '',
                        'param',
                    ),
                '',
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    '\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|bool|null',
                    '',
                    'param',
                )
                . ' from the JSON object: Field value.value must be of type Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|bool|null, array given.',
            3,
        ];
        yield 'ambiguous collection field union 2param{}' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                'non-empty-array<string, int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                    'non-empty-array<string, int>|\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates',
                    'param',
                )
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function unions2(): iterable
    {
        yield 'multiple class union rejects self type' => [
            '{"value":{}}',
            SelfClassUnionField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField from the JSON object: Field value uses multiple class types: Eventjet\Json\Test\Acceptance\Fixtures\Person, Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField. JSON does not identify which class to instantiate.',
            3,
        ];
        yield 'resource-only union resource|open-resourcevar' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<resource|open-resource>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<resource|open-resource>', 'var')
                . ' from the JSON object: Field value uses non-JSON-encodable type resource, which has no JSON representation.',
            3,
        ];
        yield 'collection union regression 5var{}' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                'list<int|list<\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface>>',
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'list<int|list<\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface>>',
                    'var',
                )
                . ' from the JSON object: Field value uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'collection union regression 7var{}' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array',
                'list<string|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|list<int>|list<string>>',
                'var',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'list<string|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|list<int>|list<string>>',
                    'var',
                )
                . ' from the JSON object: Field value uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            3,
        ];
        yield 'unsupported collection union array|\Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTargetlist<int>|\Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTargetvar{}' =>
            [
                '{}',
                CollectionDeclarationFixture::create(
                    'array|\Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget',
                    'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget',
                    'var',
                ),
                'Could not create '
                    . CollectionDeclarationFixture::create(
                        'array|\Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget',
                        'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget',
                        'var',
                    )
                    . ' from the JSON object: Field value uses abstract class Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
                3,
            ];
        yield 'class and scalar union rejects an invalid nested field value' => [
            '{"value":{"firstName":42,"lastName":"Lovelace"}}',
            ClassScalarUnionField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field value.firstName must be of type string, int given.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function unions3(): iterable
    {
        yield 'rejected native non-encodable union \Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcomevar{}' =>
            [
                '{}',
                CollectionDeclarationFixture::create(
                    '\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome',
                    '',
                    'var',
                ),
                'Could not create '
                    . CollectionDeclarationFixture::create(
                        '\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome',
                        '',
                        'var',
                    )
                    . ' from the JSON object: Field value uses non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
                3,
            ];
        yield 'rejected collection non-encodable union non-empty-array<string, \Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome>param{}' =>
            [
                '{}',
                CollectionDeclarationFixture::create(
                    'array',
                    'non-empty-array<string, \Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome>',
                    'param',
                ),
                'Could not create '
                    . CollectionDeclarationFixture::create(
                        'array',
                        'non-empty-array<string, \Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome>',
                        'param',
                    )
                    . ' from the JSON object: Field value uses non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
                3,
            ];
        yield 'ambiguous collection union list<\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\Person|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string>param{}' =>
            [
                '{}',
                CollectionDeclarationFixture::create(
                    'array',
                    'list<\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\Person|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string>',
                    'param',
                ),
                'Could not create '
                    . CollectionDeclarationFixture::create(
                        'array',
                        'list<\Eventjet\Json\Test\Acceptance\Fixtures\Coordinates|\Eventjet\Json\Test\Acceptance\Fixtures\Person|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string>',
                        'param',
                    )
                    . ' from the JSON object: Field value uses multiple class types: Eventjet\Json\Test\Acceptance\Fixtures\Coordinates, Eventjet\Json\Test\Acceptance\Fixtures\Person. JSON does not identify which class to instantiate.',
                3,
            ];
        yield 'invalid collection union value 2var' => [
            '{"value":[]}',
            CollectionDeclarationFixture::create('array|string', 'non-empty-list<int>|string', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|string', 'non-empty-list<int>|string', 'var')
                . ' from the JSON object: Field value must be of type non-empty-list<int>, empty list given.',
            3,
        ];
        yield 'nested ambiguous union int|list<int>|list<string>param{}' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<int|list<int>|list<string>>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<int|list<int>|list<string>>', 'param')
                . ' from the JSON object: Field value has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            3,
        ];
        yield 'bare ArrayObject union member var {}' => [
            '{}',
            CollectionDeclarationFixture::create('array|\ArrayObject', 'list<int>|ArrayObject', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|\ArrayObject', 'list<int>|ArrayObject', 'var')
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
    private static function unions4(): iterable
    {
        yield 'rejected collection non-encodable union list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface|int>var{}' =>
            [
                '{}',
                CollectionDeclarationFixture::create(
                    'array',
                    'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface|int>',
                    'var',
                ),
                'Could not create '
                    . CollectionDeclarationFixture::create(
                        'array',
                        'list<\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus|\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface|int>',
                        'var',
                    )
                    . ' from the JSON object: Field value uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
                3,
            ];
        yield 'mismatched collection and ambiguous enum union param {}' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string',
                'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string|bool',
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string',
                    'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus|string|bool',
                    'param',
                )
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'scalar union public property rejects object' => [
            '{"value":{}}',
            UnionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty from the JSON object: Field value must be of type string|int, stdClass given.',
            3,
        ];
        yield 'intersection type in nullable union, member absent' => [
            '{}',
            NullableIntersectionTypeField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionTypeField from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'untyped array union {}' => [
            '{}',
            ArrayScalarUnionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ArrayScalarUnionPublicProperty from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'intersection union public property' => [
            '{"value":null}',
            NullableIntersectionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionPublicProperty from the JSON object: Field value uses unsupported public property type (Countable&Iterator)|null. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group5(): iterable
    {
        yield 'collection union regression 4param{}' => [
            '{}',
            CollectionDeclarationFixture::create(
                'array|(\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface&\Countable)',
                'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface',
                'param',
            ),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array|(\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface&\Countable)',
                    'list<int>|\Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface',
                    'param',
                )
                . ' from the JSON object: Field value uses unsupported intersection type Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface&Countable. JSON does not identify a concrete class to instantiate.',
            3,
        ];
    }
}
