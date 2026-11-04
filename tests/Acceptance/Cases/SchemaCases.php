<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\Type;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionException;
use RuntimeException;

use function sprintf;

/**
 * Cases for the JSON Schema classes. Generated schemas check that decoding gives back every schema that a draft class
 * can hold, and hand-written documents check the JSON names of the keywords.
 *
 * @internal
 */
final class SchemaCases
{
    private const int GENERATED = 10;

    /**
     * @return iterable<string, array{object}>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (self::empty() as $empty) {
            $draft = $empty::class;
            yield sprintf('%s with every keyword', $draft) => [
                SchemaInputs::withEveryKeyword(new Randomizer(new Mt19937(0)), $draft),
            ];
            yield sprintf('empty %s', $draft) => [$empty];
            for ($seed = 1; $seed <= self::GENERATED; $seed++) {
                yield sprintf('generated %s, seed %d', $draft, $seed) => [
                    SchemaInputs::schema(new Randomizer(new Mt19937($seed)), $draft),
                ];
            }
        }
    }

    /**
     * @return iterable<string, array{object, string}>
     * @throws InvalidArgumentException
     */
    public static function documents(): iterable
    {
        foreach (self::empty() as $empty) {
            yield sprintf('empty %s document', $empty::class) => [$empty, '{}'];
        }
        /** @var ArrayObject<array-key, Draft4|list<string>> $dependencies */
        $dependencies = new ArrayObject(['name' => ['age'], 'age' => new Draft4(required: ['name'])]);
        $properties = new ArrayObject([
            'name' => new Draft4(type: Type::String, minLength: 1),
            '200' => new Draft4(enum: [new JsonValue(1), new JsonValue(null)]),
        ]);
        /** @var ArrayObject<array-key, Draft4> $definitions */
        $definitions = new ArrayObject(['base' => new Draft4(maximum: 1.5)]);
        yield 'draft 4 document' => [
            new Draft4(
                schema: 'http://json-schema.org/draft-04/schema#',
                id: 'https://example.com/person',
                ref: '#/definitions/base',
                default: new JsonValue(null),
                type: [Type::Object, Type::Null],
                exclusiveMaximum: true,
                items: [new Draft4(), new Draft4(type: Type::String)],
                additionalItems: false,
                properties: $properties,
                additionalProperties: new Draft4(),
                dependencies: $dependencies,
                definitions: $definitions,
            ),
            '{"$schema":"http://json-schema.org/draft-04/schema#","id":"https://example.com/person",'
                . '"$ref":"#/definitions/base","default":null,"type":["object","null"],"exclusiveMaximum":true,'
                . '"items":[{},{"type":"string"}],"additionalItems":false,'
                . '"properties":{"name":{"type":"string","minLength":1},"200":{"enum":[1,null]}},'
                . '"additionalProperties":{},"dependencies":{"name":["age"],"age":{"required":["name"]}},'
                . '"definitions":{"base":{"maximum":1.5}}}',
        ];
        /** @var ArrayObject<array-key, Draft6|bool|list<string>> $dependencies */
        $dependencies = new ArrayObject(['a' => true]);
        /** @var ArrayObject<array-key, Draft6|bool> $definitions */
        $definitions = new ArrayObject(['name' => new Draft6(pattern: '^[a-z]+$')]);
        yield 'draft 6 document' => [
            new Draft6(
                schema: 'http://json-schema.org/draft-06/schema#',
                id: 'https://example.com/list',
                const: new JsonValue(null),
                exclusiveMinimum: 0,
                items: true,
                contains: false,
                dependencies: $dependencies,
                propertyNames: new Draft6(ref: '#/definitions/name'),
                definitions: $definitions,
            ),
            '{"$schema":"http://json-schema.org/draft-06/schema#","$id":"https://example.com/list","const":null,'
                . '"exclusiveMinimum":0,"items":true,"contains":false,"dependencies":{"a":true},'
                . '"propertyNames":{"$ref":"#/definitions/name"},"definitions":{"name":{"pattern":"^[a-z]+$"}}}',
        ];
        /** @var ArrayObject<array-key, Draft7|bool> $country */
        $country = new ArrayObject(['country' => new Draft7(const: new JsonValue('AT'))]);
        /** @var ArrayObject<array-key, Draft7|bool> $postalCode */
        $postalCode = new ArrayObject(['postalCode' => new Draft7(pattern: '^[0-9]{4}$')]);
        yield 'draft 7 document' => [
            new Draft7(
                id: 'https://example.com/address',
                comment: 'Postal codes depend on the country.',
                readOnly: false,
                if: new Draft7(properties: $country),
                then: new Draft7(properties: $postalCode),
                else: false,
                contentMediaType: 'application/json',
            ),
            '{"$id":"https://example.com/address","$comment":"Postal codes depend on the country.","readOnly":false,'
                . '"if":{"properties":{"country":{"const":"AT"}}},"then":{"properties":{"postalCode":{"pattern":"^[0-9]{4}$"}}},'
                . '"else":false,"contentMediaType":"application/json"}',
        ];
        /** @var ArrayObject<array-key, Draft201909|bool> $legacy */
        $legacy = new ArrayObject(['legacy' => true]);
        /** @var ArrayObject<array-key, list<string>> $dependentRequired */
        $dependentRequired = new ArrayObject(['a' => []]);
        /** @var ArrayObject<array-key, bool> $vocabulary */
        $vocabulary = new ArrayObject(['https://json-schema.org/draft/2019-09/vocab/core' => true]);
        /** @var ArrayObject<array-key, Draft201909|bool> $defs */
        $defs = new ArrayObject();
        yield 'draft 2019-09 document' => [
            new Draft201909(
                schema: 'https://json-schema.org/draft/2019-09/schema',
                id: 'https://example.com/tree',
                recursiveRef: '#',
                anchor: 'node',
                recursiveAnchor: true,
                vocabulary: $vocabulary,
                defs: $defs,
                definitions: $legacy,
                deprecated: true,
                items: [true, new Draft201909(ref: '#node')],
                additionalItems: new Draft201909(recursiveRef: '#'),
                dependentRequired: $dependentRequired,
            ),
            '{"$schema":"https://json-schema.org/draft/2019-09/schema","$id":"https://example.com/tree",'
                . '"$recursiveRef":"#","$anchor":"node","$recursiveAnchor":true,'
                . '"$vocabulary":{"https://json-schema.org/draft/2019-09/vocab/core":true},"$defs":{},'
                . '"definitions":{"legacy":true},"deprecated":true,"items":[true,{"$ref":"#node"}],'
                . '"additionalItems":{"$recursiveRef":"#"},"dependentRequired":{"a":[]}}',
        ];
        /** @var ArrayObject<array-key, Draft202012|bool> $numericDefs */
        $numericDefs = new ArrayObject(['0' => new Draft202012(type: Type::Integer)]);
        /** @var ArrayObject<array-key, Draft202012|bool> $dependentSchemas */
        $dependentSchemas = new ArrayObject(['a' => new Draft202012(minProperties: 2)]);
        yield 'draft 2020-12 document' => [
            new Draft202012(
                schema: 'https://json-schema.org/draft/2020-12/schema',
                id: 'https://example.com/tree',
                dynamicRef: '#node',
                dynamicAnchor: 'node',
                comment: 'A tree of nodes.',
                defs: $numericDefs,
                examples: [new JsonValue([]), new JsonValue(new ArrayObject())],
                prefixItems: [new Draft202012(ref: '#/$defs/0')],
                items: false,
                unevaluatedProperties: false,
                dependentSchemas: $dependentSchemas,
                contentSchema: true,
            ),
            '{"$schema":"https://json-schema.org/draft/2020-12/schema","$id":"https://example.com/tree",'
                . '"$dynamicRef":"#node","$dynamicAnchor":"node","$comment":"A tree of nodes.",'
                . '"$defs":{"0":{"type":"integer"}},"examples":[[],{}],"prefixItems":[{"$ref":"#/$defs/0"}],'
                . '"items":false,"unevaluatedProperties":false,"dependentSchemas":{"a":{"minProperties":2}},'
                . '"contentSchema":true}',
        ];
    }

    /**
     * Documents that decode, but that json_encode() writes differently.
     *
     * @return iterable<string, array{string, object}>
     */
    public static function decoded(): iterable
    {
        foreach (self::empty() as $empty) {
            yield sprintf('JSON null keywords of %s are absent', $empty::class) => [
                '{"$schema":null,"title":null,"type":null,"enum":null,"items":null,"properties":null,"format":null}',
                $empty,
            ];
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     */
    public static function errors(): iterable
    {
        foreach (self::empty() as $empty) {
            foreach (['true', 'false'] as $json) {
                yield sprintf('%s rejects the boolean root %s', $empty::class, $json) => [
                    $json,
                    $empty::class,
                    'Expected the JSON root to be an object, got bool.',
                    DecodeErrorKind::UnexpectedRootValue,
                ];
            }
        }
        yield 'draft 4 rejects boolean schemas in allOf' => [
            '{"allOf":[true]}',
            Draft4::class,
            'Could not create '
                . Draft4::class
                . ' from the JSON object: Field allOf[0] must be of type '
                . Draft4::class
                . ', bool given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'unknown type names the path' => [
            '{"properties":{"a":{"type":"text"}}}',
            Draft7::class,
            'Could not create '
                . Draft7::class
                . ' from the JSON object: Field properties[a].type uses backed enum '
                . Type::class
                . ", which has no case with backing value 'text'.",
            DecodeErrorKind::InvalidValue,
        ];
    }

    /** @return list<Draft4|Draft6|Draft7|Draft201909|Draft202012> */
    private static function empty(): array
    {
        return [new Draft4(), new Draft6(), new Draft7(), new Draft201909(), new Draft202012()];
    }
}
