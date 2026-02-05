<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Exception\InvalidEnumValueException;
use Eventjet\Json\Exception\InvalidJsonException;
use Eventjet\Json\Exception\MissingFieldException;
use Eventjet\Json\Exception\TypeMismatchException;
use Eventjet\Json\Exception\UnsupportedTypeException;
use Eventjet\Json\Json;
use Eventjet\Test\Unit\Json\Fixtures\ClassUnion;
use Eventjet\Test\Unit\Json\Fixtures\EmptyClass;
use Eventjet\Test\Unit\Json\Fixtures\FloatBoolUnion;
use Eventjet\Test\Unit\Json\Fixtures\IntStatus;
use Eventjet\Test\Unit\Json\Fixtures\NestedClass;
use Eventjet\Test\Unit\Json\Fixtures\NoConstructor;
use Eventjet\Test\Unit\Json\Fixtures\NullableFields;
use Eventjet\Test\Unit\Json\Fixtures\NullableUnion;
use Eventjet\Test\Unit\Json\Fixtures\OptionalFields;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Eventjet\Test\Unit\Json\Fixtures\StringStatus;
use Eventjet\Test\Unit\Json\Fixtures\UnionTypes;
use Eventjet\Test\Unit\Json\Fixtures\UnionWithClass;
use Eventjet\Test\Unit\Json\Fixtures\UnitEnum;
use Eventjet\Test\Unit\Json\Fixtures\UntypedParam;
use Eventjet\Test\Unit\Json\Fixtures\WithArray;
use Eventjet\Test\Unit\Json\Fixtures\WithEnum;
use Eventjet\Test\Unit\Json\Fixtures\WithInterface;
use Eventjet\Test\Unit\Json\Fixtures\WithIntersection;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
final class JsonDecodeTest extends TestCase
{
    /**
     * @return Generator<string, array{mixed}>
     */
    public static function primitiveProvider(): Generator
    {
        yield 'string' => ['foo'];
        yield 'int' => [123];
        yield 'float' => [123.456];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'null' => [null];
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function unsupportedWithoutTypeProvider(): Generator
    {
        yield 'object' => ['{"foo": "bar"}'];
        yield 'array' => ['[1, 2, 3]'];
    }

    /**
     * @return Generator<string, array{NullableFields}>
     */
    public static function nullableFieldProvider(): Generator
    {
        yield 'with values' => [new NullableFields('John', 5)];
        yield 'with nulls' => [new NullableFields(null, null)];
    }

    /**
     * @return Generator<string, array{class-string<StringStatus|IntStatus>, StringStatus|IntStatus}>
     */
    public static function enumProvider(): Generator
    {
        yield 'string-backed' => [StringStatus::class, StringStatus::Active];
        yield 'int-backed' => [IntStatus::class, IntStatus::Published];
    }

    /**
     * @return Generator<string, array{string, class-string<StringStatus|IntStatus>}>
     */
    public static function invalidEnumValueProvider(): Generator
    {
        yield 'invalid string' => ['"invalid"', StringStatus::class];
        yield 'invalid int' => ['999', IntStatus::class];
    }

    /**
     * @return Generator<string, array{string, class-string<StringStatus|IntStatus>}>
     */
    public static function wrongTypeForEnumProvider(): Generator
    {
        yield 'int for string enum' => ['123', StringStatus::class];
        yield 'string for int enum' => ['"one"', IntStatus::class];
    }

    /**
     * @return Generator<string, array{UnionTypes|NullableUnion|FloatBoolUnion}>
     */
    public static function unionTypeProvider(): Generator
    {
        yield 'string|int with string' => [new UnionTypes('text')];
        yield 'string|int with int' => [new UnionTypes(42)];
        yield 'nullable union with null' => [new NullableUnion(null)];
        yield 'nullable union with string' => [new NullableUnion('text')];
        yield 'nullable union with int' => [new NullableUnion(42)];
        yield 'float|bool with float' => [new FloatBoolUnion(3.14)];
        yield 'float|bool with bool' => [new FloatBoolUnion(true)];
    }

    /**
     * @return Generator<string, array{string, class-string}>
     */
    public static function typeMismatchProvider(): Generator
    {
        yield 'expecting string' => ['{"required": 123}', OptionalFields::class];
        yield 'expecting int' => ['{"name": "John", "age": "thirty", "score": 95.5, "active": true}', SimpleClass::class];
        yield 'expecting float' => ['{"name": "John", "age": 30, "score": "high", "active": true}', SimpleClass::class];
        yield 'expecting bool' => ['{"name": "John", "age": 30, "score": 95.5, "active": "yes"}', SimpleClass::class];
        yield 'expecting object' => ['"not an object"', SimpleClass::class];
    }

    /**
     * @return Generator<string, array{string, class-string}>
     */
    public static function classWithoutArgsProvider(): Generator
    {
        yield 'empty constructor' => ['{}', EmptyClass::class];
        yield 'no constructor' => ['{}', NoConstructor::class];
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('primitiveProvider')]
    public function testDecodesPrimitives(mixed $value): void
    {
        $json = self::encode($value);

        self::assertSame($value, Json::decode($json));
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(InvalidJsonException::class);
        Json::decode('invalid');
    }

    #[DataProvider('unsupportedWithoutTypeProvider')]
    public function testThrowsOnComplexTypeWithoutType(string $json): void
    {
        $this->expectException(UnsupportedTypeException::class);
        Json::decode($json);
    }

    public function testDecodesSimpleClass(): void
    {
        $original = new SimpleClass('John', 30, 95.5, true);
        $json = self::encode($original);

        $result = Json::decode($json, SimpleClass::class);

        self::assertEquals($original, $result);
    }

    public function testIgnoresExtraJsonFields(): void
    {
        $original = new SimpleClass('John', 30, 95.5, true);
        /** @var array<string, mixed> $encoded */
        $encoded = json_decode(self::encode($original), true);
        $encoded['extra'] = 'ignored';
        $json = self::encode($encoded);

        $result = Json::decode($json, SimpleClass::class);

        self::assertEquals($original, $result);
    }

    public function testUsesDefaultValueForOptionalField(): void
    {
        $json = self::encode((object)['required' => 'value']);

        $result = Json::decode($json, OptionalFields::class);

        self::assertEquals(new OptionalFields('value'), $result);
    }

    public function testThrowsOnMissingRequiredField(): void
    {
        $json = self::encode((object)[]);

        $this->expectException(MissingFieldException::class);

        Json::decode($json, SimpleClass::class);
    }

    #[DataProvider('nullableFieldProvider')]
    public function testDecodesNullableFields(NullableFields $original): void
    {
        $json = self::encode($original);

        $result = Json::decode($json, NullableFields::class);

        self::assertEquals($original, $result);
    }

    /**
     * @param class-string<StringStatus|IntStatus> $class
     */
    #[DataProvider('enumProvider')]
    public function testDecodesEnums(string $class, StringStatus|IntStatus $expected): void
    {
        $json = self::encode($expected->value);

        $result = Json::decode($json, $class);

        self::assertSame($expected, $result);
    }

    /**
     * @param class-string<StringStatus|IntStatus> $class
     */
    #[DataProvider('invalidEnumValueProvider')]
    public function testThrowsOnInvalidEnumValue(string $json, string $class): void
    {
        $this->expectException(InvalidEnumValueException::class);
        Json::decode($json, $class);
    }

    /**
     * @param class-string<StringStatus|IntStatus> $class
     */
    #[DataProvider('wrongTypeForEnumProvider')]
    public function testThrowsOnWrongTypeForEnum(string $json, string $class): void
    {
        $this->expectException(TypeMismatchException::class);
        Json::decode($json, $class);
    }

    public function testDecodesNestedClass(): void
    {
        $original = new NestedClass('Parent', new SimpleClass('Child', 10, 90.0, false));
        $json = self::encode($original);

        $result = Json::decode($json, NestedClass::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesClassWithEnum(): void
    {
        $original = new WithEnum('Test', StringStatus::Active);
        $json = self::encode($original);

        $result = Json::decode($json, WithEnum::class);

        self::assertEquals($original, $result);
    }

    #[DataProvider('unionTypeProvider')]
    public function testDecodesUnionTypes(UnionTypes|NullableUnion|FloatBoolUnion $original): void
    {
        $json = self::encode($original);

        /** @psalm-suppress MixedAssignment dynamic class-string */
        $result = Json::decode($json, $original::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesUnionWithClassUsingString(): void
    {
        $original = new UnionWithClass('plain text');
        $json = self::encode($original);

        $result = Json::decode($json, UnionWithClass::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesUnionWithClassUsingObject(): void
    {
        $original = new UnionWithClass(new SimpleClass('Nested', 25, 88.8, true));
        $json = self::encode($original);

        $result = Json::decode($json, UnionWithClass::class);

        self::assertEquals($original, $result);
    }

    public function testThrowsOnTypeMismatchInUnion(): void
    {
        $this->expectException(TypeMismatchException::class);
        Json::decode('{"value": "text"}', FloatBoolUnion::class);
    }

    public function testThrowsOnClassUnion(): void
    {
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('Class unions are not supported');
        Json::decode('{"data": {"title": "test", "child": {}}}', ClassUnion::class);
    }

    public function testThrowsOnUnsupportedArraySyntax(): void
    {
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('requires two type arguments');
        Json::decode('{"items": ["a", "b"]}', WithArray::class);
    }

    public function testThrowsOnNonExistentClass(): void
    {
        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('does not exist');
        /** @psalm-suppress ArgumentTypeCoercion Testing behavior with non-existent class name */
        Json::decode('{}', 'NonExistent\\ClassName'); // @phpstan-ignore argument.type
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('typeMismatchProvider')]
    public function testThrowsOnTypeMismatch(string $json, string $class): void
    {
        $this->expectException(TypeMismatchException::class);
        Json::decode($json, $class);
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('classWithoutArgsProvider')]
    public function testDecodesClassWithoutArgs(string $json, string $class): void
    {
        $result = Json::decode($json, $class);
        self::assertInstanceOf($class, $result);
    }

    public function testDecodesIntAsFloat(): void
    {
        // JSON numbers without decimals are decoded as int by json_decode,
        // but the library converts them to float when the target type is float
        $json = self::encode((object)['name' => 'John', 'age' => 30, 'score' => 95, 'active' => true]);

        $result = Json::decode($json, SimpleClass::class);

        self::assertSame(95.0, $result->score);
    }

    public function testDecodesUntypedParam(): void
    {
        $original = new UntypedParam('anything');
        $json = self::encode($original);

        $result = Json::decode($json, UntypedParam::class);

        self::assertEquals($original, $result);
    }

    public function testThrowsOnUnitEnum(): void
    {
        $json = self::encode('One');

        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('is not backed');

        Json::decode($json, UnitEnum::class);
    }

    public function testThrowsOnIntersectionType(): void
    {
        $json = self::encode((object)['value' => (object)[]]);

        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('Unsupported type');

        Json::decode($json, WithIntersection::class);
    }

    public function testThrowsOnInterfaceType(): void
    {
        $json = self::encode((object)['value' => (object)[]]);

        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('Unknown type');

        Json::decode($json, WithInterface::class);
    }

    public function testInvalidEnumValueExceptionContainsValue(): void
    {
        $json = self::encode(999);

        try {
            Json::decode($json, IntStatus::class);
            self::fail('Expected InvalidEnumValueException');
        } catch (InvalidEnumValueException $e) {
            self::assertStringContainsString('999', $e->getMessage());
        }
    }

    public function testIntConvertedToFloatExactly(): void
    {
        // JSON numbers without decimals are decoded as int by json_decode,
        // but the library converts them to float when the target type is float
        $json = self::encode((object)['name' => 'John', 'age' => 30, 'score' => 100, 'active' => true]);

        $result = Json::decode($json, SimpleClass::class);

        self::assertSame(100.0, $result->score);
    }

    public function testUnionTypeMismatchThrows(): void
    {
        // Pass an array where string|int is expected - this hits the type mismatch branch
        $json = self::encode((object)['value' => []]);

        $this->expectException(TypeMismatchException::class);

        Json::decode($json, UnionTypes::class);
    }

    public function testUnionWithObjectNotMatchingClass(): void
    {
        // Pass int where string|SimpleClass is expected and value is not an object
        $json = self::encode((object)['data' => 123]);

        $this->expectException(TypeMismatchException::class);

        Json::decode($json, UnionWithClass::class);
    }
}
