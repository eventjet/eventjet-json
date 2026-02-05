<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Type;

use Eventjet\Json\Exception\TypeParseException;
use Eventjet\Json\Exception\UnsupportedTypeException;
use Eventjet\Json\Type\ClassType;
use Eventjet\Json\Type\ListType;
use Eventjet\Json\Type\MapType;
use Eventjet\Json\Type\PrimitiveType;
use Eventjet\Json\Type\TypeParser;
use Eventjet\Json\Type\UnionType;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeParser::class)]
#[CoversClass(PrimitiveType::class)]
#[CoversClass(ClassType::class)]
#[CoversClass(ListType::class)]
#[CoversClass(MapType::class)]
#[CoversClass(UnionType::class)]
final class TypeParserTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function primitiveProvider(): Generator
    {
        yield 'string' => ['string', 'string'];
        yield 'int' => ['int', 'int'];
        yield 'integer' => ['integer', 'int'];
        yield 'float' => ['float', 'float'];
        yield 'double' => ['double', 'float'];
        yield 'bool' => ['bool', 'bool'];
        yield 'boolean' => ['boolean', 'bool'];
        yield 'null' => ['null', 'null'];
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function unsupportedSyntaxProvider(): Generator
    {
        yield 'T[]' => ['string[]'];
        yield 'array' => ['array'];
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidTypeProvider(): Generator
    {
        yield 'empty string' => [''];
        yield 'only whitespace' => ['   '];
        yield 'empty union part' => ['string|'];
        yield 'double pipe' => ['string||int'];
        yield 'unclosed bracket' => ['list<string'];
        yield 'extra close bracket' => ['string>'];
        yield 'empty list' => ['list<>'];
    }

    #[DataProvider('primitiveProvider')]
    public function testParsesPrimitives(string $input, string $expectedName): void
    {
        $parser = new TypeParser();
        $result = $parser->parse($input);

        self::assertInstanceOf(PrimitiveType::class, $result);
        self::assertSame($expectedName, $result->name);
    }

    public function testParsesClassName(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('Foo\\Bar\\Baz');

        self::assertInstanceOf(ClassType::class, $result);
        self::assertSame('Foo\\Bar\\Baz', $result->className);
    }

    public function testResolvesShortClassName(): void
    {
        $parser = new TypeParser(static fn(string $name): string => 'Resolved\\' . $name);
        $result = $parser->parse('Baz');

        self::assertInstanceOf(ClassType::class, $result);
        self::assertSame('Resolved\\Baz', $result->className);
    }

    public function testParsesSimpleList(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<string>');

        self::assertInstanceOf(ListType::class, $result);
        self::assertInstanceOf(PrimitiveType::class, $result->inner);
        self::assertSame('string', $result->inner->name);
    }

    public function testParsesListOfClass(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<Foo\\Bar>');

        self::assertInstanceOf(ListType::class, $result);
        self::assertInstanceOf(ClassType::class, $result->inner);
        self::assertSame('Foo\\Bar', $result->inner->className);
    }

    public function testParsesNestedList(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<list<int>>');

        self::assertInstanceOf(ListType::class, $result);
        $inner = $result->inner;
        self::assertInstanceOf(ListType::class, $inner);
        self::assertInstanceOf(PrimitiveType::class, $inner->inner);
        self::assertSame('int', $inner->inner->name);
    }

    public function testParsesTripleNestedList(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<list<list<string>>>');

        self::assertInstanceOf(ListType::class, $result);
        $level1 = $result->inner;
        self::assertInstanceOf(ListType::class, $level1);
        $level2 = $level1->inner;
        self::assertInstanceOf(ListType::class, $level2);
        self::assertInstanceOf(PrimitiveType::class, $level2->inner);
        self::assertSame('string', $level2->inner->name);
    }

    public function testParsesSimpleUnion(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('string|int');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(2, $result->types);
        self::assertInstanceOf(PrimitiveType::class, $result->types[0]);
        self::assertSame('string', $result->types[0]->name);
        self::assertInstanceOf(PrimitiveType::class, $result->types[1]);
        self::assertSame('int', $result->types[1]->name);
    }

    public function testParsesTripleUnion(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('string|int|null');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(3, $result->types);
    }

    public function testParsesUnionWithClass(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('string|Foo\\Bar');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(2, $result->types);
        self::assertInstanceOf(PrimitiveType::class, $result->types[0]);
        self::assertInstanceOf(ClassType::class, $result->types[1]);
        self::assertSame('Foo\\Bar', $result->types[1]->className);
    }

    public function testParsesListWithUnionInner(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<string|int>');

        self::assertInstanceOf(ListType::class, $result);
        $inner = $result->inner;
        self::assertInstanceOf(UnionType::class, $inner);
        self::assertCount(2, $inner->types);
    }

    public function testParsesUnionWithList(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('string|list<int>');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(2, $result->types);
        self::assertInstanceOf(PrimitiveType::class, $result->types[0]);
        self::assertInstanceOf(ListType::class, $result->types[1]);
    }

    public function testParsesComplexUnionWithLists(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<string|int>|null');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(2, $result->types);
        self::assertInstanceOf(ListType::class, $result->types[0]);
        self::assertInstanceOf(PrimitiveType::class, $result->types[1]);
        self::assertSame('null', $result->types[1]->name);
    }

    public function testHandlesWhitespace(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('  string | int  ');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(2, $result->types);
    }

    public function testHandlesWhitespaceInList(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list< string >');

        self::assertInstanceOf(ListType::class, $result);
        self::assertInstanceOf(PrimitiveType::class, $result->inner);
        self::assertSame('string', $result->inner->name);
    }

    #[DataProvider('unsupportedSyntaxProvider')]
    public function testThrowsOnUnsupportedSyntax(string $input): void
    {
        $parser = new TypeParser();

        $this->expectException(TypeParseException::class);
        $this->expectExceptionMessage('Unsupported array syntax');
        $parser->parse($input);
    }

    #[DataProvider('invalidTypeProvider')]
    public function testThrowsOnInvalidType(string $input): void
    {
        $parser = new TypeParser();

        $this->expectException(TypeParseException::class);
        $parser->parse($input);
    }

    public function testPrimitiveTypeIsPrimitive(): void
    {
        self::assertTrue(PrimitiveType::isPrimitive('string'));
        self::assertTrue(PrimitiveType::isPrimitive('int'));
        self::assertTrue(PrimitiveType::isPrimitive('integer'));
        self::assertTrue(PrimitiveType::isPrimitive('float'));
        self::assertTrue(PrimitiveType::isPrimitive('double'));
        self::assertTrue(PrimitiveType::isPrimitive('bool'));
        self::assertTrue(PrimitiveType::isPrimitive('boolean'));
        self::assertTrue(PrimitiveType::isPrimitive('null'));
        self::assertFalse(PrimitiveType::isPrimitive('Foo'));
        self::assertFalse(PrimitiveType::isPrimitive('array'));
    }

    public function testPrimitiveTypeFromNameThrowsOnUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PrimitiveType::fromName('unknown');
    }

    public function testParsesSimpleMap(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array<string, int>');

        self::assertInstanceOf(MapType::class, $result);
        self::assertInstanceOf(PrimitiveType::class, $result->keyType); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertSame('string', $result->keyType->name);
        self::assertInstanceOf(PrimitiveType::class, $result->valueType);
        self::assertSame('int', $result->valueType->name);
    }

    public function testParsesMapOfClass(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array<string, Foo\\Bar>');

        self::assertInstanceOf(MapType::class, $result);
        self::assertInstanceOf(ClassType::class, $result->valueType);
        self::assertSame('Foo\\Bar', $result->valueType->className);
    }

    public function testParsesNestedMap(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array<string, array<string, int>>');

        self::assertInstanceOf(MapType::class, $result);
        $inner = $result->valueType;
        self::assertInstanceOf(MapType::class, $inner);
        self::assertInstanceOf(PrimitiveType::class, $inner->valueType);
        self::assertSame('int', $inner->valueType->name);
    }

    public function testParsesMapWithUnionValue(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array<string, string|int>');

        self::assertInstanceOf(MapType::class, $result);
        $inner = $result->valueType;
        self::assertInstanceOf(UnionType::class, $inner);
        self::assertCount(2, $inner->types);
    }

    public function testParsesUnionWithMap(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array<string, int>|null');

        self::assertInstanceOf(UnionType::class, $result);
        self::assertCount(2, $result->types);
        self::assertInstanceOf(MapType::class, $result->types[0]);
        self::assertInstanceOf(PrimitiveType::class, $result->types[1]);
        self::assertSame('null', $result->types[1]->name);
    }

    public function testParsesMapOfList(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array<string, list<int>>');

        self::assertInstanceOf(MapType::class, $result);
        $inner = $result->valueType;
        self::assertInstanceOf(ListType::class, $inner);
        self::assertInstanceOf(PrimitiveType::class, $inner->inner);
        self::assertSame('int', $inner->inner->name);
    }

    public function testParsesListOfMap(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('list<array<string, int>>');

        self::assertInstanceOf(ListType::class, $result);
        $inner = $result->inner;
        self::assertInstanceOf(MapType::class, $inner);
        self::assertInstanceOf(PrimitiveType::class, $inner->valueType);
        self::assertSame('int', $inner->valueType->name);
    }

    public function testHandlesWhitespaceInMap(): void
    {
        $parser = new TypeParser();
        $result = $parser->parse('array< string , int >');

        self::assertInstanceOf(MapType::class, $result);
        self::assertSame('string', $result->keyType->name);
        self::assertInstanceOf(PrimitiveType::class, $result->valueType);
        self::assertSame('int', $result->valueType->name);
    }

    public function testThrowsOnNonStringKeyInMap(): void
    {
        $parser = new TypeParser();

        $this->expectException(TypeParseException::class);
        $this->expectExceptionMessage('Only string keys are supported');
        $parser->parse('array<int, string>');
    }

    public function testThrowsOnMissingCommaInMap(): void
    {
        $parser = new TypeParser();

        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('requires two type arguments');
        $parser->parse('array<string>');
    }

    public function testThrowsOnEmptyMapKeyType(): void
    {
        $parser = new TypeParser();

        $this->expectException(TypeParseException::class);
        $this->expectExceptionMessage('Empty key type');
        $parser->parse('array<, int>');
    }

    public function testThrowsOnEmptyMapValueType(): void
    {
        $parser = new TypeParser();

        $this->expectException(TypeParseException::class);
        $this->expectExceptionMessage('Empty value type');
        $parser->parse('array<string, >');
    }

    public function testThrowsOnEmptyMap(): void
    {
        $parser = new TypeParser();

        $this->expectException(TypeParseException::class);
        $this->expectExceptionMessage('Empty inner type');
        $parser->parse('array<>');
    }
}
