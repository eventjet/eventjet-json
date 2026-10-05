<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use DatePeriod;
use Eventjet\Json\Test\Acceptance\Fixtures\AmbiguousMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\MixedField;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\ObjectField;
use Eventjet\Json\Test\Acceptance\Fixtures\ObjectPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StdClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedArrayObjectMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnsupportedNonEmptyMapKeyField;
use RuntimeException;
use stdClass;

use function is_string;

/** @internal */
final class UnsupportedFieldTypeCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @throws RuntimeException
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'mixed' => [new MixedField('anything'), 'value', '"anything"', 'uses unsupported type mixed'],
            'object' => [new ObjectField(new stdClass()), 'value', '{}', 'uses unsupported type object'],
            'stdClass' => [new StdClassField(new stdClass()), 'value', '{}', 'uses unsupported type stdClass'],
            'untyped' => [DatePeriod::class, 'start', '"anything"', 'uses no type declaration'],
        ] as $name => [$target, $field, $value, $declaration]) {
            $class = is_string($target) ? $target : $target::class;
            $message =
                'Could not create '
                . $class
                . ' from the JSON object: Field '
                . $field
                . ' '
                . $declaration
                . '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.';

            yield $name . ' field, member present' => ['{"' . $field . '":' . $value . '}', $class, $message, 3];
            yield $name . ' field, member absent' => ['{}', $class, $message, 3];
        }

        $ambiguousMap = new AmbiguousMapField(['key' => 'value']);
        $ambiguousMapMessage =
            'Could not create '
            . $ambiguousMap::class
            . ' from the JSON object: Field values uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.';

        yield 'ambiguous array map, member present' => [
            '{"values":{"key":"value"}}',
            $ambiguousMap::class,
            $ambiguousMapMessage,
            3,
        ];
        yield 'ambiguous array map, member absent' => [
            '{}',
            $ambiguousMap::class,
            $ambiguousMapMessage,
            3,
        ];

        foreach ([
            'ArrayObject map with integer key type' => [
                new UnsupportedArrayObjectMapField(new ArrayObject([0 => 'value'])),
                'ArrayObject',
            ],
            'non-empty array map with integer key type' => [
                new UnsupportedNonEmptyMapKeyField([0 => 'value']),
                'non-empty-array',
            ],
        ] as $name => [$map, $declaration]) {
            $message =
                'Could not create '
                . $map::class
                . ' from the JSON object: Field values uses unsupported map declaration '
                . $declaration
                . '. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.';

            yield $name . ', member present' => [
                '{"values":{"key":"value"}}',
                $map::class,
                $message,
                3,
            ];
            yield $name . ', member absent' => ['{}', $map::class, $message, 3];
        }

        $objectPublicProperty = new ObjectPublicProperty();

        yield 'object public property' => [
            '{"value":{}}',
            $objectPublicProperty::class,
            'Could not create '
                . ObjectPublicProperty::class
                . ' from the JSON object: Field value uses unsupported public property type object. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];

        yield from self::untypedPublicProperty();

        $intersectionPublicProperty = new IntersectionPublicProperty();

        foreach ([
            'intersection public property' => [$intersectionPublicProperty::class, '{}', 'Countable&Iterator'],
            'intersection union public property' => [
                NullableIntersectionPublicProperty::class,
                'null',
                '(Countable&Iterator)|null',
            ],
        ] as $name => [$class, $value, $type]) {
            yield $name => [
                '{"value":' . $value . '}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses unsupported public property type '
                    . $type
                    . '. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
                3,
            ];
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    private static function untypedPublicProperty(): iterable
    {
        $untypedClass = CollectionDeclarationFixture::create('', '', 'var');

        yield 'untyped public property' => [
            '{"value":null}',
            $untypedClass,
            'Could not create '
                . $untypedClass
                . ' from the JSON object: Field value uses unsupported public property type none. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
            3,
        ];
    }
}
