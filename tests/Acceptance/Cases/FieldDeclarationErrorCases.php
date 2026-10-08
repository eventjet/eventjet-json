<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use DatePeriod;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\MixedField;

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
            3,
        ];
        yield 'intersection constructor field is rejected even when absent' => [
            '{}',
            IntersectionTypeField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'mixed constructor field is rejected even when absent' => [
            '{}',
            MixedField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\MixedField from the JSON object: Field value uses unsupported type mixed. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            3,
        ];
        yield 'intersection public property is unsupported' => [
            '{"value":{}}',
            IntersectionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty from the JSON object: Field value uses unsupported public property type Countable&Iterator. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
        yield 'unknown public property type is unsupported' => [
            '{}',
            CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var')
                . ' from the JSON object: Field value uses unsupported public property type UnknownPublicPropertyType. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
    }
}
