<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Exception\TypeMismatchException;
use Eventjet\Json\Exception\UnsupportedTypeException;
use Eventjet\Json\Json;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Eventjet\Test\Unit\Json\Fixtures\StringStatus;
use Eventjet\Test\Unit\Json\Fixtures\WithList;
use Eventjet\Test\Unit\Json\Fixtures\WithListOfClass;
use Eventjet\Test\Unit\Json\Fixtures\WithListOfEnum;
use Eventjet\Test\Unit\Json\Fixtures\WithListUnion;
use Eventjet\Test\Unit\Json\Fixtures\WithNestedList;
use Eventjet\Test\Unit\Json\Fixtures\WithNoDocblock;
use Eventjet\Test\Unit\Json\Fixtures\WithNullableList;
use Eventjet\Test\Unit\Json\Fixtures\WithTripleNestedList;
use Eventjet\Test\Unit\Json\Fixtures\WithUnionList;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
final class ListDecodeTest extends TestCase
{
    /**
     * @return Generator<string, array{WithList|WithListUnion}>
     */
    public static function listProvider(): Generator
    {
        yield 'list of strings' => [new WithList(['a', 'b', 'c'])];
        yield 'empty list' => [new WithList([])];
        yield 'list with union types' => [new WithListUnion(['text', 42, 'more', 100])];
    }

    /**
     * @return Generator<string, array{string, list<mixed>}>
     */
    public static function rootListProvider(): Generator
    {
        yield 'list of strings' => ['list<string>', ['a', 'b', 'c']];
        yield 'list of ints' => ['list<int>', [1, 2, 3]];
        yield 'list of floats' => ['list<float>', [1.5, 2.5]];
        yield 'list of bools' => ['list<bool>', [true, false]];
        yield 'empty list' => ['list<string>', []];
        yield 'nested list' => ['list<list<int>>', [[1, 2], [3, 4]]];
        yield 'list with union' => ['list<string|int>', ['a', 1, 'b']];
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('listProvider')]
    public function testDecodesList(WithList|WithListUnion $original): void
    {
        $json = self::encode($original);

        /** @psalm-suppress MixedAssignment dynamic class-string */
        $result = Json::decode($json, $original::class);

        self::assertEquals($original, $result);
    }

    /**
     * @param list<mixed> $original
     */
    #[DataProvider('rootListProvider')]
    public function testDecodesRootList(string $type, array $original): void
    {
        $json = self::encode($original);

        /** @psalm-suppress ArgumentTypeCoercion list<T> is valid but not a class-string */
        $result = Json::decode($json, $type); // @phpstan-ignore argument.type, argument.templateType

        self::assertSame($original, $result); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testDecodesListOfClasses(): void
    {
        $original = new WithListOfClass([
            new SimpleClass('A', 1, 1.0, true),
            new SimpleClass('B', 2, 2.0, false),
        ]);
        $json = self::encode($original);

        $result = Json::decode($json, WithListOfClass::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesListOfEnums(): void
    {
        $original = new WithListOfEnum([StringStatus::Active, StringStatus::Pending]);
        $json = self::encode($original);

        $result = Json::decode($json, WithListOfEnum::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesNestedList(): void
    {
        $original = new WithNestedList([[1, 2], [3, 4], [5, 6]]);
        $json = self::encode($original);

        $result = Json::decode($json, WithNestedList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesTripleNestedList(): void
    {
        $original = new WithTripleNestedList([[['a', 'b'], ['c']], [['d']]]);
        $json = self::encode($original);

        $result = Json::decode($json, WithTripleNestedList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesUnionWithListUsingString(): void
    {
        $original = new WithUnionList('plain text');
        $json = self::encode($original);

        $result = Json::decode($json, WithUnionList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesUnionWithListUsingList(): void
    {
        $original = new WithUnionList(['a', 'b', 'c']);
        $json = self::encode($original);

        $result = Json::decode($json, WithUnionList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesNullableListWithValue(): void
    {
        $original = new WithNullableList(['a', 'b']);
        $json = self::encode($original);

        $result = Json::decode($json, WithNullableList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesNullableListWithNull(): void
    {
        $original = new WithNullableList(null);
        $json = self::encode($original);

        $result = Json::decode($json, WithNullableList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesRootListOfClasses(): void
    {
        $original = [new SimpleClass('A', 1, 1.0, true)];
        $json = self::encode($original);

        /** @psalm-suppress ArgumentTypeCoercion, MixedAssignment list<T> is valid but not a class-string */
        $result = Json::decode($json, 'list<' . SimpleClass::class . '>'); // @phpstan-ignore argument.type

        /** @var list<SimpleClass> $result @phpstan-ignore varTag.type */
        self::assertEquals($original, $result);
    }

    public function testThrowsOnArrayWithoutDocblock(): void
    {
        $json = self::encode((object)['items' => ['a']]);

        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('requires a @param docblock annotation');

        Json::decode($json, WithNoDocblock::class);
    }

    public function testThrowsOnObjectWhereListExpected(): void
    {
        $json = self::encode((object)['items' => (object)['key' => 'value']]);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected array');

        Json::decode($json, WithList::class);
    }

    public function testThrowsOnRootObjectWhereListExpected(): void
    {
        $json = self::encode((object)['key' => 'value']);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected array');

        /** @psalm-suppress ArgumentTypeCoercion list<T> is valid but not a class-string */
        Json::decode($json, 'list<string>'); // @phpstan-ignore argument.type
    }

    public function testThrowsOnObjectInNestedList(): void
    {
        $json = self::encode((object)['matrix' => [[1, 2], (object)['a' => 3]]]);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected array');

        Json::decode($json, WithNestedList::class);
    }

    public function testThrowsOnTypeMismatchInList(): void
    {
        $json = self::encode((object)['items' => ['a', 123]]);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected string');

        Json::decode($json, WithList::class);
    }

    public function testThrowsOnNonArrayForListField(): void
    {
        $json = self::encode((object)['items' => 'not an array']);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected array');

        Json::decode($json, WithList::class);
    }

    public function testThrowsOnNonArrayForRootList(): void
    {
        $json = self::encode('not an array');

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected array');

        /** @psalm-suppress ArgumentTypeCoercion list<T> is valid but not a class-string */
        Json::decode($json, 'list<string>'); // @phpstan-ignore argument.type
    }

    public function testIntConvertedToFloatInList(): void
    {
        // JSON integers are converted to PHP floats when the target type is float
        $json = self::encode([1, 2, 3]);

        /** @psalm-suppress ArgumentTypeCoercion list<T> is valid but not a class-string */
        $result = Json::decode($json, 'list<float>'); // @phpstan-ignore argument.type

        self::assertSame([1.0, 2.0, 3.0], $result); // @phpstan-ignore staticMethod.impossibleType
    }
}
