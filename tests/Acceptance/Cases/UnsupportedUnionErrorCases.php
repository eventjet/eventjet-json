<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ArrayScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionTypeField;

/** @internal */
final class UnsupportedUnionErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::nonEncodableMembers();
        yield from self::nonConcreteMembers();
        yield from self::intersectionMembers();
        yield from self::collectionDeclarations();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nonEncodableMembers(): iterable
    {
        yield 'resource union has no JSON representation' => [
            '{}',
            CollectionDeclarationFixture::create('array', 'list<resource|open-resource>', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'list<resource|open-resource>', 'var')
                . ' from the JSON object: Field value uses non-JSON-encodable type resource, which has no JSON representation.',
            3,
        ];
        yield 'native union of non-backed enums has no JSON representation' => [
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
        yield 'map union of non-backed enums has no JSON representation' => [
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
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nonConcreteMembers(): iterable
    {
        yield 'nested union cannot contain an interface item type' => [
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
        yield 'collection union cannot contain an abstract class' => [
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
        yield 'interface union member is rejected before non-backed enum members' => [
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
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function intersectionMembers(): iterable
    {
        yield 'nullable intersection member is unsupported even when absent' => [
            '{}',
            NullableIntersectionTypeField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionTypeField from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'nullable intersection public property is unsupported' => [
            '{"value":null}',
            NullableIntersectionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionPublicProperty from the JSON object: Field value uses unsupported public property type (Countable&Iterator)|null. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
        yield 'collection union cannot contain an intersection member' => [
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

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function collectionDeclarations(): iterable
    {
        yield 'union map member requires an item declaration' => [
            '{}',
            CollectionDeclarationFixture::create('array|\ArrayObject', 'list<int>|ArrayObject', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('array|\ArrayObject', 'list<int>|ArrayObject', 'var')
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
        yield 'collection union declaration must match its native members' => [
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
        yield 'array union property requires a collection declaration' => [
            '{}',
            ArrayScalarUnionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ArrayScalarUnionPublicProperty from the JSON object: Field value has a missing or unrecognized collection declaration. Use @var with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];
    }
}
