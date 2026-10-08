<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralConstantFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\SingleLiteralFields;

/** @internal */
final class LiteralTypeCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from LiteralSyntaxRoundTripCases::objects();
        yield 'constant imports and null constant' => [new LiteralConstantFields()];
        yield 'extended literal syntax and constant values' => [new ExtendedLiteralFields()];
        yield 'single literal constructor fields' => [new SingleLiteralFields()];
        yield 'literal string field' => [new LiteralFields('foo')];
        yield 'literal integer field' => [new LiteralFields(42)];
        yield 'PHPDoc true field' => [new LiteralFields(true)];
        yield 'PHPDoc false field' => [new LiteralFields(false)];
        yield 'nullable literal field' => [new LiteralFields(null)];
        $properties = new LiteralProperties();
        $properties->value = 42;
        $properties->items = ['foo', 42, true, false, null];
        yield 'literal properties and nested collections' => [$properties];
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from LiteralDeclarationErrorCases::errors();
        yield 'null constant rejects non-null value' => [
            '{"nothing":true}',
            LiteralConstantFields::class,
            'Could not create '
                . LiteralConstantFields::class
                . ' from the JSON object: Field nothing must be of type null, bool given.',
            3,
        ];
        yield 'double quoted literal rejects another string' => [
            '{"doubleQuoted":"bar"}',
            ExtendedLiteralFields::class,
            'Could not create '
                . ExtendedLiteralFields::class
                . ' from the JSON object: Field doubleQuoted must be of type "foo", string given.',
            3,
        ];
        yield 'float literal rejects another float' => [
            '{"wholeFloat":3.5}',
            ExtendedLiteralFields::class,
            'Could not create '
                . ExtendedLiteralFields::class
                . ' from the JSON object: Field wholeFloat must be of type 3.0, float given.',
            3,
        ];
        yield 'constant wildcard rejects another value' => [
            '{"constant":"baz"}',
            ExtendedLiteralFields::class,
            'Could not create '
                . ExtendedLiteralFields::class
                . ' from the JSON object: Field constant must be of type \'foo\'|\'bar\', string given.',
            3,
        ];
        yield 'single literal constructor rejects another string' => [
            '{"string":"bar"}',
            SingleLiteralFields::class,
            'Could not create '
                . SingleLiteralFields::class
                . ' from the JSON object: Field string must be of type \'foo\', string given.',
            3,
        ];
        yield 'literal field rejects another string' => [
            '{"value":"bar"}',
            LiteralFields::class,
            'Could not create '
                . LiteralFields::class
                . ' from the JSON object: Field value must be of type \'foo\'|42|true|false|null, string given.',
            3,
        ];
        yield 'literal property rejects another integer' => [
            '{"value":43}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field value must be of type \'foo\'|42, int given.',
            3,
        ];
        yield 'literal list rejects numeric string' => [
            '{"items":["42"]}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field items[0] must be of type \'foo\'|42|true|false|null, string given.',
            3,
        ];
        yield 'literal map reports key' => [
            '{"map":{"key":"bar"}}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field map[key] must be of type \'foo\', string given.',
            3,
        ];
        yield 'literal nested list reports indexes' => [
            '{"nested":[[43]]}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field nested[0][0] must be of type 42, int given.',
            3,
        ];
        yield 'literal tuple rejects opposite boolean' => [
            '{"tuple":[false,false,-42,"","a|b"]}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field tuple[0] must be of type true, bool given.',
            3,
        ];
    }
}
