<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Schema;

use Eventjet\Json\Schema\Attribute\Example;
use Eventjet\Json\Schema\Attribute\Format;
use Eventjet\Json\Schema\Attribute\MinItems;
use Eventjet\Json\Schema\Attribute\MinLength;
use Eventjet\Json\Schema\Attribute\MinProperties;
use Eventjet\Json\Schema\Attribute\OneOf;
use Eventjet\Json\Schema\Attribute\UniqueItems;
use Eventjet\Json\Schema\ClassSchemaGenerator;
use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use Eventjet\Json\Schema\JsonSchema;
use Eventjet\Json\Schema\Schema;
use Eventjet\Json\Schema\SchemaGenerator;
use Eventjet\Json\Schema\SchemaRegistry;
use Eventjet\Json\Schema\TypeNodeConverter;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithAllMetadata;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithClassLevelFormat;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithClassLevelMinItems;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithClassLevelPattern;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithClassLevelUniqueItems;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithDocblock;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithDocblockAndTags;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithExamples;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithFormat;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithMinItems;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithMinLength;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithMinProperties;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithMultiParagraphDescription;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithParamDescriptions;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithPattern;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithPropertyMetadata;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithTitleOnly;
use Eventjet\Test\Unit\Json\Fixtures\ClassWithUniqueItems;
use Eventjet\Test\Unit\Json\Fixtures\EmptyClass;
use Eventjet\Test\Unit\Json\Fixtures\IntStatus;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableMixed;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableNoDocblock;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableNoReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableUnionReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithObjectShape;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithSelfReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithShape;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithStaticReturn;
use Eventjet\Test\Unit\Json\Fixtures\JsonSerializableWithUnsealedReturn;
use Eventjet\Test\Unit\Json\Fixtures\NestedClass;
use Eventjet\Test\Unit\Json\Fixtures\NoConstructor;
use Eventjet\Test\Unit\Json\Fixtures\NullableFields;
use Eventjet\Test\Unit\Json\Fixtures\NullableUnion;
use Eventjet\Test\Unit\Json\Fixtures\OneOfInterface;
use Eventjet\Test\Unit\Json\Fixtures\OneOfSelfRef;
use Eventjet\Test\Unit\Json\Fixtures\OneOfSelfRefComposite;
use Eventjet\Test\Unit\Json\Fixtures\OneOfSelfRefLeaf;
use Eventjet\Test\Unit\Json\Fixtures\OneOfVariantA;
use Eventjet\Test\Unit\Json\Fixtures\OneOfVariantB;
use Eventjet\Test\Unit\Json\Fixtures\SelfReferencing;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Eventjet\Test\Unit\Json\Fixtures\StringStatus;
use Eventjet\Test\Unit\Json\Fixtures\UnionTypes;
use Eventjet\Test\Unit\Json\Fixtures\UnitEnum;
use Eventjet\Test\Unit\Json\Fixtures\WithArrayDocblock;
use Eventjet\Test\Unit\Json\Fixtures\WithBareArray;
use Eventjet\Test\Unit\Json\Fixtures\WithClassLevelFormat;
use Eventjet\Test\Unit\Json\Fixtures\WithClassLevelPattern;
use Eventjet\Test\Unit\Json\Fixtures\WithClassLevelUniqueItems;
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

#[CoversClass(Example::class)]
#[CoversClass(Format::class)]
#[CoversClass(MinItems::class)]
#[CoversClass(MinLength::class)]
#[CoversClass(MinProperties::class)]
#[CoversClass(OneOf::class)]
#[CoversClass(UniqueItems::class)]
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
        yield 'json serializable with object shape' => [
            new JsonSerializableWithObjectShape(StringStatus::Active),
            null,
        ];
        yield 'json serializable with self return' => [
            new JsonSerializableWithSelfReturn('root', new JsonSerializableWithSelfReturn('child', null)),
            null,
        ];
        yield 'json serializable with static return' => [
            new JsonSerializableWithStaticReturn('test'),
            null,
        ];
        yield 'class with docblock' => [new ClassWithDocblock('John', 30), null];
        yield 'class with title only' => [new ClassWithTitleOnly('abc'), null];
        yield 'class with examples' => [new ClassWithExamples('John', 30), null];
        yield 'class with format' => [new ClassWithFormat('2024-01-01T00:00:00Z', 'john@example.com'), null];
        yield 'class with class-level format' => [
            new WithClassLevelFormat(new ClassWithClassLevelFormat('550e8400-e29b-41d4-a716-446655440000')),
            null,
        ];
        yield 'class with pattern' => [new ClassWithPattern('hello'), null];
        yield 'class with class-level pattern' => [
            new WithClassLevelPattern(new ClassWithClassLevelPattern('42')),
            null,
        ];
        yield 'class with unique items' => [new ClassWithUniqueItems(['a', 'b', 'c']), null];
        yield 'class with class-level unique items' => [
            new WithClassLevelUniqueItems(new ClassWithClassLevelUniqueItems(['a', 'b'])),
            null,
        ];
        yield 'class with property metadata' => [new ClassWithPropertyMetadata('john@example.com'), null];
        yield 'class with all metadata' => [
            new ClassWithAllMetadata('john@example.com', '2024-01-01T00:00:00Z'),
            null,
        ];
        yield 'class with param descriptions' => [
            new ClassWithParamDescriptions('John', 30, 'john@example.com', 'none'),
            null,
        ];
        yield 'oneOf variant A' => [new OneOfVariantA('hello'), OneOfInterface::class];
        yield 'oneOf variant B' => [new OneOfVariantB(42), OneOfInterface::class];
        yield 'oneOf self-ref leaf' => [new OneOfSelfRefLeaf('x'), OneOfSelfRef::class];
        yield 'oneOf self-ref composite' => [
            new OneOfSelfRefComposite([new OneOfSelfRefLeaf('a')]),
            OneOfSelfRef::class,
        ];

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
        yield 'positive-int' => [42, 'positive-int'];
        yield 'non-negative-int' => [0, 'non-negative-int'];
        yield 'non-positive-int' => [0, 'non-positive-int'];
        yield 'negative-int' => [-1, 'negative-int'];
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

        yield 'tuple array{string, int}' => [['hello', 42], 'array{string, int}'];
        yield 'list{string, int}' => [['hello', 42], 'list{string, int}'];
        yield 'object{name: string, age?: int}' => [['name' => 'John'], 'object{name: string, age?: int}'];
        yield 'object{name: string, age?: int} with age' => [
            ['name' => 'John', 'age' => 30],
            'object{name: string, age?: int}',
        ];

        yield 'identifier array' => [['anything'], 'array'];
        yield 'identifier list' => [['a', 'b'], 'list'];
        yield 'identifier iterable' => [['a'], 'iterable'];
        yield 'identifier object' => [['key' => 'value'], 'object'];
        yield 'identifier scalar string' => ['hello', 'scalar'];
        yield 'identifier scalar number' => [42, 'scalar'];
        yield 'identifier scalar bool' => [true, 'scalar'];
        yield 'identifier number int' => [42, 'number'];
        yield 'identifier number float' => [3.14, 'number'];
        yield 'identifier numeric int' => [42, 'numeric'];
        yield 'identifier numeric string' => ['42.5', 'numeric'];
        yield 'identifier array-key string' => ['key', 'array-key'];
        yield 'identifier array-key int' => [42, 'array-key'];
        yield 'identifier non-zero-int positive' => [5, 'non-zero-int'];
        yield 'identifier non-zero-int negative' => [-3, 'non-zero-int'];
        yield 'identifier literal-string' => ['hello', 'literal-string'];
        yield 'identifier callable-string' => ['strlen', 'callable-string'];
        yield 'identifier lowercase-string' => ['hello', 'lowercase-string'];
        yield 'identifier non-falsy-string' => ['hello', 'non-falsy-string'];
        yield 'identifier truthy-string' => ['hello', 'truthy-string'];

        yield 'iterable<string>' => [['a', 'b'], 'iterable<string>'];
        yield 'iterable<string, int>' => [['a' => 1], 'iterable<string, int>'];
        yield 'int-mask<1, 2, 4> 0' => [0, 'int-mask<1, 2, 4>'];
        yield 'int-mask<1, 2, 4> 3' => [3, 'int-mask<1, 2, 4>'];
        yield 'int-mask<1, 2, 4> 7' => [7, 'int-mask<1, 2, 4>'];
    }

    /**
     * @return Generator<string, array{mixed, string}>
     */
    public static function rejectionProvider(): Generator
    {
        yield 'string rejects int' => [42, 'string'];
        yield 'int rejects string' => ['hello', 'int'];
        yield 'bool rejects int' => [1, 'bool'];
        yield 'null rejects empty string' => ['', 'null'];

        yield 'non-falsy-string rejects empty' => ['', 'non-falsy-string'];
        yield 'non-falsy-string rejects zero' => ['0', 'non-falsy-string'];
        yield 'truthy-string rejects empty' => ['', 'truthy-string'];
        yield 'truthy-string rejects zero' => ['0', 'truthy-string'];
        yield 'lowercase-string rejects uppercase' => ['Hello', 'lowercase-string'];
        yield 'numeric-string rejects alpha' => ['hello', 'numeric-string'];

        yield 'positive-int rejects zero' => [0, 'positive-int'];
        yield 'positive-int rejects negative' => [-1, 'positive-int'];
        yield 'negative-int rejects zero' => [0, 'negative-int'];
        yield 'negative-int rejects positive' => [1, 'negative-int'];
        yield 'non-negative-int rejects negative' => [-1, 'non-negative-int'];
        yield 'non-positive-int rejects positive' => [1, 'non-positive-int'];
        yield 'non-zero-int rejects zero' => [0, 'non-zero-int'];

        yield 'scalar rejects null' => [null, 'scalar'];
        yield 'scalar rejects array' => [[1], 'scalar'];
        yield 'number rejects string' => ['hello', 'number'];
        yield 'numeric rejects non-numeric string' => ['hello', 'numeric'];
        yield 'array-key rejects bool' => [true, 'array-key'];

        yield 'list<int> rejects list of strings' => [['a'], 'list<int>'];
        yield 'tuple rejects too few items' => [['hello'], 'array{string, int}'];
        yield 'tuple rejects extra items' => [['hello', 42, 'extra'], 'array{string, int}'];
        yield 'tuple rejects wrong types' => [[42, 'hello'], 'array{string, int}'];

        yield 'object shape rejects missing required' => [['age' => 30], 'object{name: string, age?: int}'];
        yield 'array shape rejects missing required' => [['n' => 1], 'array{foo: string, n: int}'];

        yield 'int-mask rejects invalid combination' => [8, 'int-mask<1, 2, 4>'];
        yield 'int-mask rejects non-integer' => ['3', 'int-mask<1, 2, 4>'];
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

    #[DataProvider('rejectionProvider')]
    public function testGeneratedSchemaRejectsInvalidValue(mixed $value, string $type): void
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate($type);
        /** @var bool|object $jsonSchema */
        $jsonSchema = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        /** @var mixed $jsonData */
        $jsonData = json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $result = $validator->validate($jsonData, $jsonSchema);
        self::assertFalse(
            $result->isValid(),
            sprintf(
                "Schema should have rejected value.\nSchema: %s\nData: %s",
                json_encode($schema, JSON_THROW_ON_ERROR),
                json_encode($value, JSON_THROW_ON_ERROR),
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

    public function testNeverReturnAliases(): void
    {
        $generator = new SchemaGenerator();
        foreach (['never-return', 'never-returns', 'no-return'] as $alias) {
            $schema = $generator->generate($alias);
            self::assertFalse(
                json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
                sprintf('Expected %s to produce false schema', $alias),
            );
        }
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

    public function testClassDocblockProducesTitleAndDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithDocblock::class);
        self::assertSame('A person.', $json['title']);
        self::assertSame('Represents a person with a name and age.', $json['description']);
    }

    public function testDocblockTagsAreNotIncludedInDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithDocblockAndTags::class);
        self::assertSame('A tagged class.', $json['title']);
        self::assertSame('Has a description.', $json['description']);
    }

    public function testMultiParagraphDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithMultiParagraphDescription::class);
        self::assertSame('A complex entity.', $json['title']);
        $expectedDescription = "This is the first paragraph\nthat spans multiple lines.\n\nThis is the second paragraph.";
        self::assertSame($expectedDescription, $json['description']);
    }

    public function testClassDocblockTitleOnlyNoDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithTitleOnly::class);
        self::assertSame('A simple widget.', $json['title']);
        self::assertArrayNotHasKey('description', $json);
    }

    public function testClassExamplesAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithExamples::class);
        self::assertArrayHasKey('examples', $json);
        self::assertIsArray($json['examples']);
        self::assertCount(2, $json['examples']);
    }

    public function testPropertyFormatAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithFormat::class);
        $properties = $this->properties($json);
        self::assertSame('date-time', $properties['createdAt']['format']);
        self::assertSame('email', $properties['email']['format']);
    }

    public function testClassLevelFormatAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithClassLevelFormat::class);
        self::assertSame('uuid', $json['format']);
    }

    public function testPropertyPatternAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithPattern::class);
        $properties = $this->properties($json);
        self::assertSame('^[a-z]+$', $properties['slug']['pattern']);
    }

    public function testClassLevelPatternAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithClassLevelPattern::class);
        self::assertSame('[1-9][0-9]*', $json['pattern']);
    }

    public function testPropertyUniqueItemsAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithUniqueItems::class);
        $properties = $this->properties($json);
        self::assertTrue($properties['tags']['uniqueItems']);
    }

    public function testClassLevelUniqueItemsAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithClassLevelUniqueItems::class);
        self::assertTrue($json['uniqueItems']);
    }

    public function testPropertyMetadata(): void
    {
        $json = $this->generateAndDecode(ClassWithPropertyMetadata::class);
        $email = $this->property($json, 'email');
        self::assertSame("The user's email address.", $email['title']);
        self::assertSame('Must be a valid email.', $email['description']);
        self::assertIsArray($email['examples']);
        self::assertCount(2, $email['examples']);
        self::assertSame('john@example.com', $email['examples'][0]);
        self::assertSame('jane@example.com', $email['examples'][1]);
    }

    public function testClassWithNoDocblockHasNoTitleOrDescription(): void
    {
        $json = $this->generateAndDecode(SimpleClass::class);
        self::assertArrayNotHasKey('title', $json);
        self::assertArrayNotHasKey('description', $json);
    }

    public function testParamDescriptionBecomesPropertyDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithParamDescriptions::class);
        $name = $this->property($json, 'name');
        self::assertArrayNotHasKey('title', $name);
        self::assertSame("The user's full name", $name['description']);
    }

    public function testPropertyDocblockTakesPriorityOverParamDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithParamDescriptions::class);
        $age = $this->property($json, 'age');
        self::assertSame('The age of the person.', $age['title']);
        self::assertSame('Must be positive.', $age['description']);
    }

    public function testVarOnlyPropertyGetsParamDescription(): void
    {
        $json = $this->generateAndDecode(ClassWithParamDescriptions::class);
        $email = $this->property($json, 'email');
        self::assertArrayNotHasKey('title', $email);
        self::assertSame("The user's email address", $email['description']);
    }

    public function testAllMetadataCombined(): void
    {
        $json = $this->generateAndDecode(ClassWithAllMetadata::class);
        self::assertSame('A user account.', $json['title']);
        self::assertSame('Represents a registered user in the system.', $json['description']);
        self::assertIsArray($json['examples']);
        self::assertCount(1, $json['examples']);
        $properties = $this->properties($json);
        self::assertSame('email', $properties['email']['format']);
        self::assertSame('date-time', $properties['createdAt']['format']);
        $email = $this->property($json, 'email');
        self::assertSame("The user's email address.", $email['title']);
        self::assertIsArray($email['examples']);
        self::assertCount(1, $email['examples']);
    }

    public function testOneOfProducesAnyOf(): void
    {
        $json = $this->generateAndDecode(OneOfInterface::class);
        self::assertArrayHasKey('anyOf', $json);
        self::assertIsArray($json['anyOf']);
        self::assertCount(2, $json['anyOf']);
    }

    public function testNonEmptyStringThrows(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('#[MinLength(1)]');
        $generator->generate('non-empty-string');
    }

    public function testNonEmptyListThrows(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('#[MinItems(1)]');
        $generator->generate('non-empty-list<int>');
    }

    public function testNonEmptyArrayThrows(): void
    {
        $generator = new SchemaGenerator();
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('#[MinItems(1)]');
        $generator->generate('non-empty-array<string, int>');
    }

    public function testPropertyMinItemsAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithMinItems::class);
        $properties = $this->properties($json);
        self::assertSame(1, $properties['tags']['minItems']);
    }

    public function testPropertyMinLengthAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithMinLength::class);
        $properties = $this->properties($json);
        self::assertSame(1, $properties['name']['minLength']);
    }

    public function testClassLevelMinItemsAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithClassLevelMinItems::class);
        self::assertSame(1, $json['minItems']);
    }

    public function testPropertyMinPropertiesAttribute(): void
    {
        $json = $this->generateAndDecode(ClassWithMinProperties::class);
        $properties = $this->properties($json);
        self::assertSame(1, $properties['counts']['minProperties']);
    }

    /**
     * @param class-string $className
     * @return array<string, mixed>
     */
    private function generateAndDecode(string $className): array
    {
        $generator = new SchemaGenerator();
        $schema = $generator->generate($className);
        /** @var array<string, mixed> */
        return json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $json
     * @return array<string, array<string, mixed>>
     */
    private function properties(array $json): array
    {
        $properties = $json['properties'];
        self::assertIsArray($properties);
        /** @var array<string, array<string, mixed>> */
        return $properties;
    }

    /**
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    private function property(array $json, string $name): array
    {
        $properties = $this->properties($json);
        self::assertArrayHasKey($name, $properties);
        return $properties[$name];
    }
}
