<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Schema;

use Eventjet\Json\Schema\ClassSchemaGenerator;
use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use Eventjet\Json\Schema\JsonSchema;
use Eventjet\Json\Schema\Schema;
use Eventjet\Json\Schema\SchemaGenerator;
use Eventjet\Json\Schema\SchemaRegistry;
use Eventjet\Json\Schema\TypeNodeConverter;
use Eventjet\Test\Unit\Json\Fixtures\EmptyClass;
use Eventjet\Test\Unit\Json\Fixtures\IntStatus;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableMixed;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableNoDocblock;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableNoReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableUnionReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithShape;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithUnsealedReturn;
use Eventjet\Test\Unit\Json\Fixtures\NestedClass;
use Eventjet\Test\Unit\Json\Fixtures\NoConstructor;
use Eventjet\Test\Unit\Json\Fixtures\NullableFields;
use Eventjet\Test\Unit\Json\Fixtures\NullableUnion;
use Eventjet\Test\Unit\Json\Fixtures\SelfReferencing;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Eventjet\Test\Unit\Json\Fixtures\StringStatus;
use Eventjet\Test\Unit\Json\Fixtures\UnionTypes;
use Eventjet\Test\Unit\Json\Fixtures\UnitEnum;
use Eventjet\Test\Unit\Json\Fixtures\WithArrayDocblock;
use Eventjet\Test\Unit\Json\Fixtures\WithBareArray;
use Eventjet\Test\Unit\Json\Fixtures\WithEnum;
use Eventjet\Test\Unit\Json\Fixtures\WithList;
use Eventjet\Test\Unit\Json\Fixtures\WithListOfClass;
use Eventjet\Test\Unit\Json\Fixtures\WithMap;
use Eventjet\Test\Unit\Json\Fixtures\WithMapOfClass;
use Eventjet\Test\Unit\Json\Fixtures\WithNestedList;
use Eventjet\Test\Unit\Json\Fixtures\WithNonVarDocblock;
use Eventjet\Test\Unit\Json\Fixtures\WithNullableDocblock;
use Eventjet\Test\Unit\Json\Fixtures\WithStaticProperty;
use Eventjet\Test\Unit\Json\Fixtures\WithUnionDocblock;
use Eventjet\Test\Unit\Json\Fixtures\WithUntypedProperty;
use Eventjet\Test\Unit\Json\Fixtures\WithVarDocblock;
use Generator;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

#[CoversClass(SchemaGenerator::class)]
#[CoversClass(ClassSchemaGenerator::class)]
#[CoversClass(TypeNodeConverter::class)]
#[CoversClass(SchemaRegistry::class)]
#[CoversClass(Schema::class)]
#[CoversClass(JsonSchema::class)]
#[CoversClass(UnsupportedTypeException::class)]
final class SchemaGeneratorTest extends TestCase
{
    /**
     * @return Generator<string, array{mixed, string|null}>
     */
    public static function validationProvider(): Generator
    {
        yield 'simple class' => [new SimpleClass('John', 30, 9.5, true), null];
        yield 'with list' => [new WithList(['a', 'b', 'c']), null];
        yield 'with map' => [new WithMap(['x' => 1, 'y' => 2]), null];
        yield 'string enum' => [StringStatus::Active, null];
        yield 'int enum' => [IntStatus::Published, null];
        yield 'nested class' => [new NestedClass('title', new SimpleClass('John', 30, 9.5, true)), null];
        yield 'nullable with values' => [new NullableFields('John', 5), null];
        yield 'nullable with nulls' => [new NullableFields(null, null), null];
        yield 'union types' => [new UnionTypes('hello'), null];
        yield 'union types int' => [new UnionTypes(42), null];
        yield 'with enum' => [new WithEnum('test', StringStatus::Active), null];
        yield 'with list of class' => [
            new WithListOfClass([new SimpleClass('A', 1, 1.0, true), new SimpleClass('B', 2, 2.0, false)]),
            null,
        ];
        yield 'with map of class' => [
            new WithMapOfClass(['a' => new SimpleClass('A', 1, 1.0, true)]),
            null,
        ];
        yield 'with nested list' => [new WithNestedList([[1, 2], [3, 4]]), null];
        yield 'empty class' => [new EmptyClass(), null];
        yield 'no constructor class' => [new NoConstructor(), null];
        yield 'json serializable with return docblock' => [new JsonSerializableWithReturn('Alice', 25), null];
        yield 'json serializable no docblock' => [new JsonSerializableNoDocblock('hello'), null];
        yield 'json serializable mixed' => [new JsonSerializableMixed(), null];
        yield 'with var docblock' => [new WithVarDocblock(), null];
        yield 'with static property' => [new WithStaticProperty('test'), null];
        yield 'with bare array' => [new WithBareArray([1, 2, 3]), null];
        yield 'self referencing' => [new SelfReferencing('root', new SelfReferencing('child', null)), null];
        yield 'json serializable no return' => [new JsonSerializableNoReturn('hello'), null];
        yield 'json serializable with shape' => [new JsonSerializableWithShape('Alice'), null];
        yield 'json serializable union return' => [new JsonSerializableUnionReturn('hello'), null];
        yield 'with array docblock' => [new WithArrayDocblock(['a', 'b']), null];
        yield 'with non-var docblock' => [new WithNonVarDocblock('test'), null];
        yield 'with nullable docblock' => [new WithNullableDocblock('test'), null];
        yield 'with nullable docblock null' => [new WithNullableDocblock(null), null];
        yield 'with union docblock' => [new WithUnionDocblock('test'), null];
        yield 'with untyped property' => [new WithUntypedProperty('test'), null];
        yield 'nullable union with string' => [new NullableUnion('hello'), null];
        yield 'nullable union with null' => [new NullableUnion(null), null];
        yield 'json serializable with unsealed return' => [new JsonSerializableWithUnsealedReturn('Alice'), null];

        yield 'string type' => ['hello', 'string'];
        yield 'int type' => [42, 'int'];
        yield 'integer alias' => [42, 'integer'];
        yield 'float type' => [3.14, 'float'];
        yield 'double alias' => [3.14, 'double'];
        yield 'bool type' => [true, 'bool'];
        yield 'boolean alias' => [false, 'boolean'];
        yield 'null type' => [null, 'null'];
        yield 'mixed type' => ['anything', 'mixed'];
        yield 'true literal' => [true, 'true'];
        yield 'false literal' => [false, 'false'];
        yield 'class-string' => ['SomeClass', 'class-string'];
        yield 'numeric-string' => ['42.5', 'numeric-string'];

        yield 'list<int>' => [[1, 2, 3], 'list<int>'];
        yield 'list<string>' => [['a', 'b'], 'list<string>'];
        yield 'array<string, int>' => [['x' => 1, 'y' => 2], 'array<string, int>'];
        yield 'non-empty-string' => ['hello', 'non-empty-string'];
        yield 'positive-int' => [42, 'positive-int'];
        yield 'non-negative-int' => [0, 'non-negative-int'];
        yield 'non-positive-int' => [0, 'non-positive-int'];
        yield 'negative-int' => [-1, 'negative-int'];
        yield 'non-empty-list<int>' => [[1], 'non-empty-list<int>'];
        yield 'non-empty-array<string, int>' => [['a' => 1], 'non-empty-array<string, int>'];
        yield 'class-string<Foo>' => ['SomeClass', 'class-string<int>'];
        yield 'array shape' => [['foo' => 'bar', 'n' => 1], 'array{foo: string, n: int}'];
        yield 'array shape optional' => [['foo' => 'bar'], 'array{foo: string, n?: int}'];
        yield 'string|int with string' => ['hello', 'string|int'];
        yield 'string|int with int' => [42, 'string|int'];
        yield 'nullable string with value' => ['hello', '?string'];
        yield 'nullable string with null' => [null, '?string'];
        yield 'int<0, 100>' => [50, 'int<0, 100>'];
        yield 'int<min, 100>' => [50, 'int<min, 100>'];
        yield 'int<0, max>' => [50, 'int<0, max>'];
        yield 'string[]' => [['a', 'b'], 'string[]'];
        yield 'const string literal' => ['foo', "'foo'"];
        yield 'const int literal' => [42, '42'];
        yield 'const string union' => ['a', "'a'|'b'|'c'"];
        yield 'array<string> single param' => [['a', 'b'], 'array<string>'];
        yield 'float const' => [3.14, '3.14'];
        yield 'const int and float enum' => [42, '42|3.14'];
        yield 'unsealed array shape' => [['name' => 'test', 'extra' => 'stuff'], 'array{name: string, ...}'];
    }

    #[DataProvider('validationProvider')]
    public function testGeneratedSchemaAcceptsEncodedValue(mixed $value, string|null $type): void
    {
        $generator = new SchemaGenerator();
        if ($type !== null) {
            $schema = $generator->generate($type);
        } else {
            self::assertIsObject($value);
            $schema = $generator->generate($value::class);
        }
        /** @var bool|object $jsonSchema */
        $jsonSchema = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        /** @var mixed $jsonData */
        $jsonData = json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $result = $validator->validate($jsonData, $jsonSchema);
        self::assertTrue(
            $result->isValid(),
            sprintf(
                "Schema validation failed.\nSchema: %s\nData: %s\nError: %s",
                json_encode($schema, JSON_THROW_ON_ERROR),
                json_encode($value, JSON_THROW_ON_ERROR),
                $result->error() !== null ? (string)$result->error() : 'none',
            ),
        );
    }

    public function testJsonSchemaConvenience(): void
    {
        $schema = JsonSchema::generate(SimpleClass::class);
        /** @var object $jsonSchema */
        $jsonSchema = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        /** @var mixed $data */
        $data = json_decode(
            json_encode(new SimpleClass('John', 30, 9.5, true), JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );
        $validator = new Validator();
        $result = $validator->validate($data, $jsonSchema);
        self::assertTrue($result->isValid());
    }

    public function testInlineRootFalse(): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate(SimpleClass::class, false);
        /** @var object $json */
        $json = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertObjectHasProperty('$ref', $json);
        self::assertObjectHasProperty('$defs', $json);
    }

    public function testInlineRootFalseWithNoDefs(): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate(EmptyClass::class, false);
        /** @var object $json */
        $json = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertObjectHasProperty('$ref', $json);
    }

    public function testUnitEnumThrows(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(UnsupportedTypeException::class);
        $generator->generate(UnitEnum::class);
    }

    public function testIntersectionTypeThrows(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(UnsupportedTypeException::class);
        $generator->generate('Countable&Traversable');
    }

    public function testNeverType(): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate('never');
        self::assertFalse(json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testTypeStringWithClassReferenceProducesDefs(): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate('list<' . SimpleClass::class . '>');
        /** @var object $json */
        $json = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertObjectHasProperty('$defs', $json);
    }

    public function testReusedGeneratorReturnsSameSchemaForClass(): void
    {
        $generator = new SchemaGenerator();
        $first = $generator->generate(SimpleClass::class);
        $second = $generator->generate(SimpleClass::class);
        self::assertEquals(
            json_encode($first, JSON_THROW_ON_ERROR),
            json_encode($second, JSON_THROW_ON_ERROR),
        );
    }

    public function testReusedGeneratorResolvesAlreadyRegisteredClass(): void
    {
        $generator = new SchemaGenerator();
        $generator->generate(SimpleClass::class);
        $schema = $generator->generate('list<' . SimpleClass::class . '>');
        /** @var object $json */
        $json = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertObjectHasProperty('$defs', $json);
    }
}
