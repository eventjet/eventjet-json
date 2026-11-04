<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\Type;
use Eventjet\Json\Test\Acceptance\Fixtures\FailingSerializer;

use const INF;
use const NAN;

/**
 * Schemas that do not compile, values that are not JSON, and the failures that validation reports.
 *
 * @internal
 */
final class ValidatorErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Draft4|Draft6|Draft7|Draft201909|Draft202012|bool, (Closure(string): mixed)|null, string, DecodeError|null}>
     */
    public static function schemaErrors(): iterable
    {
        yield 'reference without a loader' => [
            new Draft202012(ref: 'https://example.com/missing'),
            null,
            'Could not resolve https://example.com/missing, because there is no loader.',
            null,
        ];
        yield 'reference the loader does not find' => [
            new Draft202012(ref: 'https://example.com/missing#/$defs/a'),
            static fn(): null => null,
            'Could not resolve https://example.com/missing: the loader found no document.',
            null,
        ];
        yield 'reference to a missing subschema' => [
            new Draft202012(ref: '#/$defs/missing'),
            null,
            'The reference #/$defs/missing points to no schema.',
            null,
        ];
        yield 'reference to a missing anchor' => [
            new Draft202012(ref: '#missing'),
            null,
            'The reference #missing points to no schema.',
            null,
        ];
        yield 'reference to a value that is not a schema' => [
            new Draft202012(ref: '#/minLength', minLength: 1),
            null,
            'The reference #/minLength points to a value that is not a schema.',
            null,
        ];
        yield 'reference to itself' => [
            new Draft202012(ref: '#'),
            null,
            'The schema at # refers to itself without moving into the value, so it would never finish.',
            null,
        ];
        yield 'loop through allOf' => [
            new Draft7(allOf: [new Draft7(ref: '#')]),
            null,
            'The schema at # refers to itself without moving into the value, so it would never finish.',
            null,
        ];
        yield 'negative minLength' => [
            new Draft202012(minLength: -1),
            null,
            'The keyword at #/minLength must be a non-negative integer.',
            null,
        ];
        yield 'fractional maxItems' => [
            new Draft6(maxItems: 1.5),
            null,
            'The keyword at #/maxItems must be a non-negative integer.',
            null,
        ];
        yield 'whole float minLength in Draft 4' => [
            new Draft4(minLength: 2.0),
            null,
            'The keyword at #/minLength must be a non-negative integer.',
            null,
        ];
        yield 'multipleOf of zero' => [
            new Draft202012(multipleOf: 0),
            null,
            'The keyword at #/multipleOf must be a number greater than 0.',
            null,
        ];
        yield 'PCRE-only regex' => [
            new Draft202012(pattern: '(?i)a'),
            null,
            'The regex at #/pattern is not valid ECMA-262: (?i)a',
            null,
        ];
        /** @var ArrayObject<array-key, Draft202012|bool> $patternProperties */
        $patternProperties = new ArrayObject(['[z-a]' => true]);
        yield 'invalid regex in patternProperties' => [
            new Draft202012(patternProperties: $patternProperties),
            null,
            'The regex at #/patternProperties is not valid ECMA-262: [z-a]',
            null,
        ];
        yield '$schema of another draft' => [
            new Draft7(schema: 'https://json-schema.org/draft/2020-12/schema'),
            null,
            'The $schema https://json-schema.org/draft/2020-12/schema names Draft 2020-12, but the schema is a Draft 7 schema.',
            null,
        ];
        /** @var ArrayObject<array-key, Draft202012|bool> $defs */
        $defs = new ArrayObject([
            'a' => new Draft202012(schema: 'https://json-schema.org/draft/2019-09/schema', id: 'https://example.com/a'),
        ]);
        yield '$schema of another draft in an embedded resource' => [
            new Draft202012(defs: $defs),
            null,
            'The $schema https://json-schema.org/draft/2019-09/schema names Draft 2019-09, but the schema is a Draft 2020-12 schema.',
            null,
        ];
        yield 'custom meta-schema without a loader' => [
            new Draft202012(schema: 'https://example.com/meta'),
            null,
            'Could not resolve https://example.com/meta, because there is no loader.',
            null,
        ];
        yield 'custom meta-schema of another draft' => [
            new Draft202012(schema: 'https://example.com/meta'),
            static fn(): Draft201909 => new Draft201909(),
            'The $schema https://example.com/meta names Draft 2019-09, but the schema is a Draft 2020-12 schema.',
            null,
        ];
        /** @var ArrayObject<array-key, bool> $vocabulary */
        $vocabulary = new ArrayObject(['https://example.com/vocab' => true]);
        yield 'custom meta-schema with an unknown required vocabulary' => [
            new Draft202012(schema: 'https://example.com/meta'),
            static fn(): Draft202012 => new Draft202012(vocabulary: $vocabulary),
            'The meta-schema https://example.com/meta requires the unknown vocabulary https://example.com/vocab.',
            null,
        ];
        $decodeError = new DecodeError(DecodeErrorKind::InvalidJson, 'Invalid JSON: Syntax error');
        yield 'document the loader cannot decode' => [
            new Draft202012(ref: 'https://example.com/broken'),
            static fn(): DecodeError => $decodeError,
            'Could not load https://example.com/broken: Invalid JSON: Syntax error',
            $decodeError,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{mixed}>
     */
    public static function unsupportedValues(): iterable
    {
        yield 'infinity' => [INF];
        yield 'NaN' => [NAN];
        yield 'nested infinity' => [['a' => [1, -INF]]];
        yield 'invalid UTF-8' => ["\xFF"];
        yield 'invalid UTF-8 member name' => [["\xFF" => 1]];
        yield 'member name starting with a null byte' => [["\0a" => 1]];
        $nested = [];
        for ($level = 0; $level < 512; $level++) {
            $nested = [$nested];
        }
        yield 'nested more than 512 levels' => [$nested];
        yield 'jsonSerialize() that throws' => [new FailingSerializer()];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Draft4|Draft6|Draft7|Draft201909|Draft202012|bool, mixed, list<array{string, string, string, string}>}>
     */
    public static function failures(): iterable
    {
        yield 'false schema' => [false, 1, [['', '', '#', 'No value is allowed here.']]];
        yield 'type at the root' => [
            new Draft202012(type: Type::String),
            1,
            [['', '/type', '#/type', 'The value must be of type string.']],
        ];
        /** @var ArrayObject<array-key, Draft202012|bool> $properties */
        $properties = new ArrayObject([
            'name' => new Draft202012(minLength: 2),
            'tags' => new Draft202012(items: new Draft202012(type: [Type::String, Type::Null])),
        ]);
        yield 'every failure of an object' => [
            new Draft202012(required: ['id'], properties: $properties),
            ['name' => 'a', 'tags' => ['ok', 3]],
            [
                ['', '/required', '#/required', 'The member "id" is required.'],
                [
                    '/name',
                    '/properties/name/minLength',
                    '#/properties/name/minLength',
                    'The value must have at least 2 characters, but it has 1.',
                ],
                [
                    '/tags/1',
                    '/properties/tags/items/type',
                    '#/properties/tags/items/type',
                    'The value must be of type string or null.',
                ],
            ],
        ];
        /** @var ArrayObject<array-key, Draft202012|bool> $defs */
        $defs = new ArrayObject(['positive' => new Draft202012(exclusiveMinimum: 0)]);
        yield 'through a reference' => [
            new Draft202012(
                id: 'https://example.com/root',
                defs: $defs,
                items: new Draft202012(ref: '#/$defs/positive'),
            ),
            [1, 0],
            [[
                '/1',
                '/items/$ref/exclusiveMinimum',
                'https://example.com/root#/$defs/positive/exclusiveMinimum',
                'The value must be greater than 0.',
            ]],
        ];
        yield 'anyOf without a match' => [
            new Draft202012(anyOf: [new Draft202012(type: Type::String), new Draft202012(minimum: 10)]),
            5,
            [
                ['', '/anyOf/0/type',    '#/anyOf/0/type',    'The value must be of type string.'],
                ['', '/anyOf/1/minimum', '#/anyOf/1/minimum', 'The value must be at least 10.'],
                ['', '/anyOf',           '#/anyOf',           'The value must match at least one of the "anyOf" schemas.'],
            ],
        ];
        /** @var ArrayObject<array-key, Draft202012|bool> $properties */
        $properties = new ArrayObject(['a/b~c' => false]);
        yield 'member name that needs escaping' => [
            new Draft202012(properties: $properties),
            ['a/b~c' => 1],
            [['/a~1b~0c', '/properties/a~1b~0c', '#/properties/a~1b~0c', 'No value is allowed here.']],
        ];
    }
}
