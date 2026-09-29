<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Schema;

use Eventjet\Json\Json;
use Eventjet\Json\JsonError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class SchemaTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function schemas(): iterable
    {
        foreach ([Draft4::class, Draft6::class, Draft7::class, Draft201909::class, Draft202012::class] as $class) {
            foreach ([
                '{}',
                '{"type":"object","properties":{},"required":[""],"additionalProperties":false}',
                '{"default":null,"enum":[null,false,{},[],{"0":[],"":{}}]}',
                '{"$ref":"","title":"","pattern":"","uniqueItems":false,"maxItems":0}',
                '{"type":["integer","null"],"allOf":[{}],"anyOf":[{}],"oneOf":[{}],"not":{}}',
                '{"x-custom":{"array":[{},[],null,1e1000],"0":{},"":"a\"b"}}',
                '{"maximum":123456789012345678901234567890.123456789,"minimum":-0,"multipleOf":1e-1000}',
            ] as $index => $json) {
                yield $class . '/' . $index => [$class, $json];
            }
        }
        yield 'Draft 4' => [Draft4::class, '{"id":"urn:test","minimum":0,"exclusiveMinimum":true,"items":[{}],"additionalItems":false,"dependencies":{"x":["y"],"z":{}},"definitions":{"a":{}}}'];
        yield 'Draft 6' => [Draft6::class, '{"$id":"urn:test","exclusiveMinimum":0.5,"const":null,"items":[true,false],"additionalItems":false,"propertyNames":true,"contains":false,"dependencies":{"a":false,"b":[]}}'];
        yield 'Draft 7' => [Draft7::class, '{"$comment":"","if":true,"then":false,"else":{},"readOnly":false,"writeOnly":true,"contentEncoding":"base64","contentMediaType":"application/json"}'];
        yield '2019-09' => [Draft201909::class, '{"$recursiveRef":"#","$recursiveAnchor":true,"items":[false],"additionalItems":true,"unevaluatedItems":false,"minContains":0,"dependentSchemas":{"a":false},"dependentRequired":{"a":[]}}'];
        yield '2020-12' => [Draft202012::class, '{"$schema":"https://json-schema.org/draft/2020-12/schema","$dynamicAnchor":"node","$dynamicRef":"#node","$vocabulary":{"urn:custom":false},"$defs":{"0":false,"":{}},"prefixItems":[{},false],"items":false,"unevaluatedProperties":false,"contentSchema":true}'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('schemas')]
    public function testRoundTrip(string $class, string $json): void
    {
        $schema = Json::decode($json, $class);
        self::assertJsonStringEqualsJsonString($json, Json::encode($schema));
    }

    public function testDecodedFieldsHaveUsefulPhpTypes(): void
    {
        $schema = Json::decode('{"type":"object","properties":{"name":{"type":"string"},"0":false},"required":["name"],"default":null}', Draft202012::class);

        self::assertSame(Type::Object, $schema->type);
        self::assertNotNull($schema->properties);
        self::assertInstanceOf(Draft202012::class, $schema->properties['name']);
        self::assertSame(Type::String, $schema->properties['name']->type);
        self::assertFalse($schema->properties[0]);
        self::assertSame(['name'], $schema->required);
        self::assertNull($schema->default);
        self::assertStringContainsString('"default":null', Json::encode($schema));
        self::assertStringNotContainsString('"const"', Json::encode($schema));
    }

    public function testLegacyDefinitionsAreTypedAndKeepTheirKeyword(): void
    {
        $json = '{"definitions":{"name":{"type":"string","definitions":{"nested":{}}},"0":false,"":true},"$defs":{"name":{"type":"integer"}}}';
        $schema = Json::decode($json, Draft201909::class);

        self::assertNotNull($schema->definitions);
        self::assertInstanceOf(Draft201909::class, $schema->definitions['name']);
        self::assertSame(Type::String, $schema->definitions['name']->type);
        self::assertNotNull($schema->definitions['name']->definitions);
        self::assertInstanceOf(Draft201909::class, $schema->definitions['name']->definitions['nested']);
        self::assertFalse($schema->definitions[0]);
        self::assertTrue($schema->definitions['']);
        self::assertNotNull($schema->defs);
        self::assertInstanceOf(Draft201909::class, $schema->defs['name']);
        self::assertSame(Type::Integer, $schema->defs['name']->type);
        self::assertJsonStringEqualsJsonString($json, Json::encode($schema));
    }

    public function testEmptyLegacyDefinitionsRemainAnObject(): void
    {
        $schema = Json::decode('{"definitions":{}}', Draft201909::class);
        self::assertSame([], $schema->definitions);
        self::assertSame('{"definitions":{}}', Json::encode($schema));
    }

    public function testLiteralObjectsAndArraysRemainDifferent(): void
    {
        $schema = Json::decode('{"default":{},"const":[]}', Draft202012::class);
        self::assertInstanceOf(stdClass::class, $schema->default);
        self::assertSame([], $schema->const);
        self::assertSame('{"default":{},"const":[]}', Json::encode($schema));
    }

    public function testRootBooleanUsesExplicitUnionTarget(): void
    {
        self::assertFalse(Json::decode('false', Draft202012::class . '|bool'));
        self::assertTrue(Json::decode('true', Draft202012::class . '|bool'));
        self::assertInstanceOf(Draft202012::class, Json::decode('{}', Draft202012::class . '|bool'));
    }

    public function testClassTargetRejectsBooleanRoot(): void
    {
        $this->expectException(JsonError::class);
        Json::decode('false', Draft202012::class);
    }

    public function testNumbersRetainTheirOriginalPrecision(): void
    {
        $json = '{"maximum":123456789012345678901234567890.123456789,"maxItems":1e1000,"default":[-0,1.0,1e-1000]}';
        $schema = Json::decode($json, Draft202012::class);
        self::assertJsonStringEqualsJsonString($json, Json::encode($schema));
        self::assertStringContainsString('123456789012345678901234567890.123456789', Json::encode($schema));
        self::assertStringContainsString('1e1000', Json::encode($schema));
        self::assertStringContainsString('[-0,1.0,1e-1000]', Json::encode($schema));
    }

    public function testDecodedSchemaIsReadonly(): void
    {
        $schema = Json::decode('{"type":"object","properties":{"name":{"type":"string"}},"x-custom":{"empty":{}}}', Draft202012::class);
        self::assertNotNull($schema->properties);
        self::assertInstanceOf(Draft202012::class, $schema->properties['name']);
        self::assertTrue((new ReflectionClass($schema))->isReadOnly());
        self::assertTrue((new ReflectionClass($schema->properties['name']))->isReadOnly());
        self::assertJsonStringEqualsJsonString('{"type":"object","properties":{"name":{"type":"string"}},"x-custom":{"empty":{}}}', Json::encode($schema));
    }

    public function testSchemaCanBeConstructedWithNamedArguments(): void
    {
        $schema = new Draft202012(type: Type::Object, additionalProperties: false, properties: []);
        self::assertSame(Type::Object, $schema->type);
        self::assertSame([], $schema->properties);
        self::assertFalse($schema->additionalProperties);
    }
}
