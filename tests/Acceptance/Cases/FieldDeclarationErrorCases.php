<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayIterator;
use DatePeriod;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\MixedField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;

/** @internal */
final class FieldDeclarationErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'untyped constructor field is rejected even when absent' => [
            '{}',
            DatePeriod::class,
            'Could not create DatePeriod from the JSON object: Field start uses no type declaration. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'non-nullable non-backed enum field is rejected even when absent' => [
            '{}',
            CollectionDeclarationFixture::create('\\' . NonBackedStatus::class, '', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('\\' . NonBackedStatus::class, '', 'param')
                . ' from the JSON object: Field value uses non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'intersection constructor field is rejected even when absent' => [
            '{}',
            new IntersectionTypeField(new ArrayIterator())::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'mixed constructor field is rejected even when absent' => [
            '{}',
            new MixedField(null)::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\MixedField from the JSON object: Field value uses unsupported type mixed. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'intersection public property is unsupported' => [
            '{"value":{}}',
            new IntersectionPublicProperty()::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty from the JSON object: Field value uses unsupported public property type Countable&Iterator. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'unknown public property type is unsupported' => [
            '{}',
            CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var')
                . ' from the JSON object: Field value uses unsupported public property type UnknownPublicPropertyType. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
    }
}
