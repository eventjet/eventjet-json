<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use DatePeriod;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\MixedField;
use Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty;

/** @internal */
final class FieldsErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'cached public fields still validate values at depth 1' => [
            '[{}, {"integer":"wrong"}]',
            JsonType::array(ConstructorlessPublicProperties::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties from the JSON object: Field [1].integer must be of type int, string given.',
            3,
        ];
        yield 'untyped field, member absent' => [
            '{}',
            DatePeriod::class,
            'Could not create DatePeriod from the JSON object: Field start uses no type declaration. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            3,
        ];
        yield 'intersection type field, member absent' => [
            '{}',
            IntersectionTypeField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'mixed field, member absent' => [
            '{}',
            MixedField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\MixedField from the JSON object: Field value uses unsupported type mixed. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            3,
        ];
        yield 'intersection public property' => [
            '{"value":{}}',
            IntersectionPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty from the JSON object: Field value uses unsupported public property type Countable&Iterator. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
        yield 'constructor parameter rejects static property' => [
            '{}',
            StaticConstructorParameterProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty from the JSON object: Constructor parameter value has no same-named declared public instance property. The target class does not expose a stable JSON member from which the argument can be recovered.',
            3,
        ];
        yield 'unknown public property {}' => [
            '{}',
            CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var')
                . ' from the JSON object: Field value uses unsupported public property type UnknownPublicPropertyType. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
    }
}
