<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Schema;

use Closure;
use Eventjet\Json\Schema\Schema;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Schema::class)]
final class SchemaTest extends TestCase
{
    /**
     * @return Generator<string, array{Closure(): Schema, mixed}>
     */
    public static function serializationProvider(): Generator
    {
        yield 'string' => [static fn() => Schema::string(), (object)['type' => 'string']];
        yield 'integer' => [static fn() => Schema::integer(), (object)['type' => 'integer']];
        yield 'number' => [static fn() => Schema::number(), (object)['type' => 'number']];
        yield 'boolean' => [static fn() => Schema::boolean(), (object)['type' => 'boolean']];
        yield 'null' => [static fn() => Schema::null(), (object)['type' => 'null']];
        yield 'mixed' => [static fn() => Schema::mixed(), true];
        yield 'never' => [static fn() => Schema::never(), false];
        yield 'const string' => [static fn() => Schema::const('foo'), (object)['const' => 'foo']];
        yield 'const int' => [static fn() => Schema::const(42), (object)['const' => 42]];
        yield 'const null' => [static fn() => Schema::const(null), (object)['const' => null]];
        yield 'enum' => [static fn() => Schema::enum(['a', 'b', 'c']), (object)['enum' => ['a', 'b', 'c']]];
        yield 'array' => [
            static fn() => Schema::array(Schema::string()),
            (object)['type' => 'array', 'items' => (object)['type' => 'string']],
        ];
        yield 'object' => [
            static fn() => Schema::object(['name' => Schema::string()], ['name']),
            (object)[
                'type' => 'object',
                'properties' => (object)['name' => (object)['type' => 'string']],
                'required' => ['name'],
                'additionalProperties' => false,
            ],
        ];
        yield 'object without required' => [
            static fn() => Schema::object(['name' => Schema::string()], []),
            (object)[
                'type' => 'object',
                'properties' => (object)['name' => (object)['type' => 'string']],
                'additionalProperties' => false,
            ],
        ];
        yield 'map' => [
            static fn() => Schema::map(Schema::integer()),
            (object)['type' => 'object', 'additionalProperties' => (object)['type' => 'integer']],
        ];
        yield 'anyOf' => [
            static fn() => Schema::anyOf([Schema::string(), Schema::integer()]),
            (object)['anyOf' => [(object)['type' => 'string'], (object)['type' => 'integer']]],
        ];
        yield 'ref' => [
            static fn() => Schema::ref('#/$defs/Foo'),
            (object)['$ref' => '#/$defs/Foo'],
        ];
        yield 'withFormat' => [
            static fn() => Schema::string()->withFormat('date-time'),
            (object)['type' => 'string', 'format' => 'date-time'],
        ];
        yield 'withMinimum' => [
            static fn() => Schema::integer()->withMinimum(0),
            (object)['type' => 'integer', 'minimum' => 0],
        ];
        yield 'withMaximum' => [
            static fn() => Schema::integer()->withMaximum(100),
            (object)['type' => 'integer', 'maximum' => 100],
        ];
        yield 'withExclusiveMinimum' => [
            static fn() => Schema::integer()->withExclusiveMinimum(0),
            (object)['type' => 'integer', 'exclusiveMinimum' => 0],
        ];
        yield 'withExclusiveMaximum' => [
            static fn() => Schema::integer()->withExclusiveMaximum(0),
            (object)['type' => 'integer', 'exclusiveMaximum' => 0],
        ];
        yield 'withMinLength' => [
            static fn() => Schema::string()->withMinLength(1),
            (object)['type' => 'string', 'minLength' => 1],
        ];
        yield 'withMaxLength' => [
            static fn() => Schema::string()->withMaxLength(255),
            (object)['type' => 'string', 'maxLength' => 255],
        ];
        yield 'withMinItems' => [
            static fn() => Schema::array(Schema::string())->withMinItems(1),
            (object)['type' => 'array', 'items' => (object)['type' => 'string'], 'minItems' => 1],
        ];
        yield 'withMaxItems' => [
            static fn() => Schema::array(Schema::string())->withMaxItems(10),
            (object)['type' => 'array', 'items' => (object)['type' => 'string'], 'maxItems' => 10],
        ];
        yield 'withMinProperties' => [
            static fn() => Schema::map(Schema::string())->withMinProperties(1),
            (object)['type' => 'object', 'additionalProperties' => (object)['type' => 'string'], 'minProperties' => 1],
        ];
        yield 'withPattern' => [
            static fn() => Schema::string()->withPattern('^[a-z]+$'),
            (object)['type' => 'string', 'pattern' => '^[a-z]+$'],
        ];
        yield 'withDefs' => [
            static fn() => Schema::ref('#/$defs/Foo')->withDefs(['Foo' => Schema::string()]),
            (object)['$ref' => '#/$defs/Foo', '$defs' => ['Foo' => (object)['type' => 'string']]],
        ];
        yield 'withTitle' => [
            static fn() => Schema::string()->withTitle('A name'),
            (object)['title' => 'A name', 'type' => 'string'],
        ];
        yield 'withDescription' => [
            static fn() => Schema::string()->withDescription('The full name'),
            (object)['description' => 'The full name', 'type' => 'string'],
        ];
        yield 'withExamples' => [
            static fn() => Schema::string()->withExamples(['hello', 'world']),
            (object)['type' => 'string', 'examples' => ['hello', 'world']],
        ];
        yield 'withTitle and description' => [
            static fn() => Schema::string()->withTitle('Name')->withDescription('Full name'),
            (object)['title' => 'Name', 'description' => 'Full name', 'type' => 'string'],
        ];
        yield 'defs on non-ref schema' => [
            static fn() => Schema::object(['name' => Schema::string()], ['name'])->withDefs(
                ['Other' => Schema::integer()],
            ),
            (object)[
                '$defs' => ['Other' => (object)['type' => 'integer']],
                'type' => 'object',
                'properties' => (object)['name' => (object)['type' => 'string']],
                'required' => ['name'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @param Closure(): Schema $schemaFactory
     */
    #[DataProvider('serializationProvider')]
    public function testSerialization(Closure $schemaFactory, mixed $expected): void
    {
        $schema = $schemaFactory();
        /** @var mixed $actual */
        $actual = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        /** @var mixed $expectedJson */
        $expectedJson = json_decode(json_encode($expected, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertEquals($expectedJson, $actual);
    }
}
