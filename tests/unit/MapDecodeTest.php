<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Exception\TypeMismatchException;
use Eventjet\Json\Json;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Eventjet\Test\Unit\Json\Fixtures\WithListOfMap;
use Eventjet\Test\Unit\Json\Fixtures\WithListOrMap;
use Eventjet\Test\Unit\Json\Fixtures\WithMap;
use Eventjet\Test\Unit\Json\Fixtures\WithMapOfClass;
use Eventjet\Test\Unit\Json\Fixtures\WithMapOfList;
use Eventjet\Test\Unit\Json\Fixtures\WithNestedMap;
use Eventjet\Test\Unit\Json\Fixtures\WithNullableMap;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
final class MapDecodeTest extends TestCase
{
    /**
     * @return Generator<string, array{string, array<string, mixed>}>
     */
    public static function rootMapProvider(): Generator
    {
        yield 'map of strings' => ['array<string, string>', ['a' => 'foo', 'b' => 'bar']];
        yield 'map of ints' => ['array<string, int>', ['x' => 1, 'y' => 2]];
        yield 'map of floats' => ['array<string, float>', ['pi' => 3.14, 'e' => 2.72]];
        yield 'map of bools' => ['array<string, bool>', ['yes' => true, 'no' => false]];
        yield 'empty map' => ['array<string, string>', []];
        yield 'nested map' => ['array<string, array<string, int>>', ['outer' => ['inner' => 42]]];
        yield 'map with union' => ['array<string, string|int>', ['a' => 'text', 'b' => 123]];
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('rootMapProvider')]
    public function testDecodesRootMap(string $type, array $original): void
    {
        $json = self::encode((object)$original);

        /** @psalm-suppress ArgumentTypeCoercion array<string, T> is valid but not a class-string */
        $result = Json::decode($json, $type); // @phpstan-ignore argument.type, argument.templateType

        self::assertSame($original, $result); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testDecodesMapField(): void
    {
        $original = new WithMap(['a' => 1, 'b' => 2, 'c' => 3]);
        $json = self::encode($original);

        $result = Json::decode($json, WithMap::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesEmptyMapField(): void
    {
        $expected = new WithMap([]);
        // Note: json_encode([]) produces "[]" (array), but we need "{}" (object) for maps
        // So we encode as an object explicitly
        $json = '{"items":{}}';

        $result = Json::decode($json, WithMap::class);

        self::assertEquals($expected, $result);
    }

    public function testDecodesMapOfClasses(): void
    {
        $original = new WithMapOfClass([
            'first' => new SimpleClass('A', 1, 1.0, true),
            'second' => new SimpleClass('B', 2, 2.0, false),
        ]);
        $json = self::encode($original);

        $result = Json::decode($json, WithMapOfClass::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesNestedMap(): void
    {
        $original = new WithNestedMap([
            'outer1' => ['inner1' => 1, 'inner2' => 2],
            'outer2' => ['inner3' => 3],
        ]);
        $json = self::encode($original);

        $result = Json::decode($json, WithNestedMap::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesMapOfList(): void
    {
        $original = new WithMapOfList([
            'a' => [1, 2, 3],
            'b' => [4, 5],
        ]);
        $json = self::encode($original);

        $result = Json::decode($json, WithMapOfList::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesListOfMap(): void
    {
        $original = new WithListOfMap([
            ['a' => 1, 'b' => 2],
            ['c' => 3],
        ]);
        $json = self::encode($original);

        $result = Json::decode($json, WithListOfMap::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesNullableMapWithValue(): void
    {
        $original = new WithNullableMap(['key' => 'value']);
        $json = self::encode($original);

        $result = Json::decode($json, WithNullableMap::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesNullableMapWithNull(): void
    {
        $original = new WithNullableMap(null);
        $json = self::encode($original);

        $result = Json::decode($json, WithNullableMap::class);

        self::assertEquals($original, $result);
    }

    public function testThrowsOnArrayWhereMapExpected(): void
    {
        $json = self::encode(['a', 'b', 'c']);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected object');

        /** @psalm-suppress ArgumentTypeCoercion array<string, T> is valid but not a class-string */
        Json::decode($json, 'array<string, int>'); // @phpstan-ignore argument.type
    }

    public function testThrowsOnTypeMismatchInMapValue(): void
    {
        $json = self::encode((object)['a' => 1, 'b' => 'not an int']);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected int');

        /** @psalm-suppress ArgumentTypeCoercion array<string, T> is valid but not a class-string */
        Json::decode($json, 'array<string, int>'); // @phpstan-ignore argument.type
    }

    public function testThrowsOnTypeMismatchInMapField(): void
    {
        $json = self::encode((object)['items' => (object)['a' => 'not an int']]);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected int');

        Json::decode($json, WithMap::class);
    }

    public function testThrowsOnNonObjectForMapField(): void
    {
        $json = self::encode((object)['items' => [1, 2, 3]]);

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected object');

        Json::decode($json, WithMap::class);
    }

    public function testIntConvertedToFloatInMapValue(): void
    {
        $json = self::encode((object)['a' => 1, 'b' => 2]);

        /** @psalm-suppress ArgumentTypeCoercion array<string, T> is valid but not a class-string */
        $result = Json::decode($json, 'array<string, float>'); // @phpstan-ignore argument.type

        self::assertSame(['a' => 1.0, 'b' => 2.0], $result); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testDecodesRootMapOfClasses(): void
    {
        $original = [
            'first' => new SimpleClass('A', 1, 1.0, true),
        ];
        $json = self::encode((object)$original);

        /** @psalm-suppress ArgumentTypeCoercion, MixedAssignment array<string, T> is valid but not a class-string */
        $result = Json::decode($json, 'array<string, ' . SimpleClass::class . '>'); // @phpstan-ignore argument.type

        /** @var array<string, SimpleClass> $result @phpstan-ignore varTag.type */
        self::assertEquals($original, $result);
    }

    public function testDecodesListOrMapUnionWithList(): void
    {
        $original = new WithListOrMap(['a', 'b', 'c']);
        $json = self::encode($original);

        $result = Json::decode($json, WithListOrMap::class);

        self::assertEquals($original, $result);
    }

    public function testDecodesListOrMapUnionWithMap(): void
    {
        $expected = new WithListOrMap(['x' => 'hello', 'y' => 'world']);
        $json = '{"data":{"x":"hello","y":"world"}}';

        $result = Json::decode($json, WithListOrMap::class);

        self::assertEquals($expected, $result);
    }
}
