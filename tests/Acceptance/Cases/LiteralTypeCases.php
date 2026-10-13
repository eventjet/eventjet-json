<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralConstantFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\SingleLiteralFields;

use const PHP_INT_MAX;

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
        yield 'constant imports and null constant' => [new LiteralConstantFields()];
        yield from LiteralSyntaxRoundTripCases::objects();
        yield from LiteralConstantCases::objects();
        yield 'extended literal syntax and constant values' => [new ExtendedLiteralFields()];
        yield 'single literal constructor fields' => [new SingleLiteralFields()];
        yield 'literal string field' => [new LiteralFields('foo')];
        yield 'literal integer field' => [new LiteralFields(42)];
        yield 'PHPDoc true field' => [new LiteralFields(true)];
        yield 'PHPDoc false field' => [new LiteralFields(false)];
        yield 'nullable literal field' => [new LiteralFields(null)];
        foreach (['param', 'var'] as $tag) {
            yield 'literal collection union keeps other native members ' . $tag => [
                CollectionDeclarationFixture::object('array|string|int', "list<42>|'foo'|int", $tag, 7),
            ];
            yield 'collection union keeps every named native member ' . $tag => [
                CollectionDeclarationFixture::object('array|string|int', 'list<42>|string|int', $tag, 7),
            ];
            yield 'ordinary PHPDoc does not narrow a native union ' . $tag => [
                CollectionDeclarationFixture::object('int|string|null', 'null|int', $tag, 'abc'),
            ];
            yield 'unknown names leave a literal declaration unchecked ' . $tag => [
                CollectionDeclarationFixture::object('string', "UnknownLiteralClass|'x'", $tag, 'y'),
            ];
            $name = $tag === 'param' ? ' $value' : '';
            yield 'a word that only starts with the tag name declares nothing ' . $tag => [
                CollectionDeclarationFixture::object('int', '/** @' . $tag . '1' . $name . ' */', $tag, 2),
            ];
        }
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
        yield from self::mismatches();
        yield from self::repeatedTags();
        yield from self::collectionUnionErrors();
        yield 'null constant rejects non-null value' => [
            '{"nothing":true}',
            LiteralConstantFields::class,
            'Could not create '
                . LiteralConstantFields::class
                . ' from the JSON object: Field nothing must be of type null, bool given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'double quoted literal rejects another string' => [
            '{"doubleQuoted":"bar"}',
            ExtendedLiteralFields::class,
            'Could not create '
                . ExtendedLiteralFields::class
                . ' from the JSON object: Field doubleQuoted must be of type "foo", string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'float literal rejects another float' => [
            '{"wholeFloat":3.5}',
            ExtendedLiteralFields::class,
            'Could not create '
                . ExtendedLiteralFields::class
                . ' from the JSON object: Field wholeFloat must be of type 3.0, float given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'constant wildcard rejects another value' => [
            '{"constant":"baz"}',
            ExtendedLiteralFields::class,
            'Could not create '
                . ExtendedLiteralFields::class
                . ' from the JSON object: Field constant must be of type \'foo\'|\'bar\', string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'single literal constructor rejects another string' => [
            '{"string":"bar"}',
            SingleLiteralFields::class,
            'Could not create '
                . SingleLiteralFields::class
                . ' from the JSON object: Field string must be of type \'foo\', string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'literal field rejects another string' => [
            '{"value":"bar"}',
            LiteralFields::class,
            'Could not create '
                . LiteralFields::class
                . ' from the JSON object: Field value must be of type \'foo\'|42|true|false|null, string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'literal property rejects another integer' => [
            '{"value":43}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field value must be of type \'foo\'|42, int given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'literal list rejects numeric string' => [
            '{"items":["42"]}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field items[0] must be of type \'foo\'|42|true|false|null, string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'literal map reports key' => [
            '{"map":{"key":"bar"}}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field map[key] must be of type \'foo\', string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'literal nested list reports indexes' => [
            '{"nested":[[43]]}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field nested[0][0] must be of type 42, int given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'literal tuple rejects opposite boolean' => [
            '{"tuple":[false,false,-42,"","a|b"]}',
            LiteralProperties::class,
            'Could not create '
                . LiteralProperties::class
                . ' from the JSON object: Field tuple[0] must be of type true, bool given.',
            DecodeErrorKind::InvalidValue->value,
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws \RuntimeException
     */
    private static function mismatches(): iterable
    {
        foreach ([
            ['array', 'list<null>', '[true]', 'value[0]', 'null', 'bool'],
            ['array', 'list<42>', '[null]', 'value[0]', '42', 'null'],
            ['int', '42', '43', 'value', '42', 'int'],
            ['int', 'PHP_INT_MAX', '43', 'value', (string) PHP_INT_MAX, 'int'],
            ['string|null', '(null)', '"x"', 'value', 'null', 'string'],
            ['string', '\\' . ExtendedLiteralFields::class . '::WORD_*', '"baz"', 'value', "'foo'|'bar'", 'string'],
        ] as [$native, $declaration, $value, $path, $expected, $actual]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                yield 'literal mismatch ' . $declaration . ' ' . $tag => [
                    '{"value":' . $value . '}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field '
                        . $path
                        . ' must be of type '
                        . $expected
                        . ', '
                        . $actual
                        . ' given.',
                    DecodeErrorKind::InvalidValue->value,
                ];
            }
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws \RuntimeException
     */
    private static function repeatedTags(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            $name = $tag === 'param' ? ' $value' : '';
            $doc = "/**\n * @" . $tag . ' 42' . $name . "\n * @" . $tag . ' 43' . $name . "\n */";
            $class = CollectionDeclarationFixture::create('int', $doc, $tag);
            yield 'the first of repeated tags declares the field ' . $tag => [
                '{"value":43}',
                $class,
                'Could not create ' . $class . ' from the JSON object: Field value must be of type 42, int given.',
                DecodeErrorKind::InvalidValue->value,
            ];
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws \RuntimeException
     */
    private static function collectionUnionErrors(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            $class = CollectionDeclarationFixture::create('array|string', "list<42>|'foo'", $tag);
            yield 'literal collection union member rejects other strings ' . $tag => [
                '{"value":"bar"}',
                $class,
                'Could not create '
                    . $class
                    . " from the JSON object: Field value must be of type list<42>|'foo', string given.",
                DecodeErrorKind::InvalidValue->value,
            ];
            $class = CollectionDeclarationFixture::create(
                'array',
                'list<\\' . Coordinates::class . '|\\' . Person::class . '|1|1.0>',
                $tag,
            );
            yield 'literal overlap errors precede class union errors ' . $tag => [
                '{}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value has overlapping literal union members. JSON cannot identify which PHP type to restore.',
                DecodeErrorKind::UnsupportedDeclaration->value,
            ];
        }
    }
}
