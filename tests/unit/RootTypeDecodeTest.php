<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Json;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
final class RootTypeDecodeTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string, mixed}>
     */
    public static function rootUnionProvider(): Generator
    {
        yield 'string|null with string' => ['string|null', '"hello"', 'hello'];
        yield 'string|null with null' => ['string|null', 'null', null];
        yield 'int|string with int' => ['int|string', '42', 42];
        yield 'int|string with string' => ['int|string', '"text"', 'text'];
        yield 'string|int|null with null' => ['string|int|null', 'null', null];
        yield 'list<string>|null with list' => ['list<string>|null', '["a","b"]', ['a', 'b']];
        yield 'list<string>|null with null' => ['list<string>|null', 'null', null];
        yield 'array<string, int>|null with map' => ['array<string, int>|null', '{"a":1}', ['a' => 1]];
        yield 'array<string, int>|null with null' => ['array<string, int>|null', 'null', null];
    }

    #[DataProvider('rootUnionProvider')]
    public function testDecodesRootUnion(string $type, string $json, mixed $expected): void
    {
        /** @psalm-suppress ArgumentTypeCoercion, MixedAssignment Type expressions are valid but not class-strings */
        $result = Json::decode($json, $type); // @phpstan-ignore argument.type, argument.templateType

        self::assertSame($expected, $result);
    }

    public function testDecodesRootListOrMapUnionWithList(): void
    {
        $json = '["a","b","c"]';

        /** @psalm-suppress ArgumentTypeCoercion Type expressions are valid but not class-strings */
        $result = Json::decode($json, 'list<string>|array<string, string>'); // @phpstan-ignore argument.type

        self::assertSame(['a', 'b', 'c'], $result); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testDecodesRootListOrMapUnionWithMap(): void
    {
        $json = '{"x":"hello","y":"world"}';

        /** @psalm-suppress ArgumentTypeCoercion Type expressions are valid but not class-strings */
        $result = Json::decode($json, 'list<string>|array<string, string>'); // @phpstan-ignore argument.type

        self::assertSame(['x' => 'hello', 'y' => 'world'], $result); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testDecodesRootPrimitive(): void
    {
        /** @psalm-suppress ArgumentTypeCoercion Type expressions are valid but not class-strings */
        self::assertSame('hello', Json::decode('"hello"', 'string')); // @phpstan-ignore argument.type, staticMethod.impossibleType
        /** @psalm-suppress ArgumentTypeCoercion */
        self::assertSame(42, Json::decode('42', 'int')); // @phpstan-ignore argument.type, staticMethod.impossibleType
        /** @psalm-suppress ArgumentTypeCoercion */
        self::assertSame(3.14, Json::decode('3.14', 'float')); // @phpstan-ignore argument.type, staticMethod.impossibleType
        /** @psalm-suppress ArgumentTypeCoercion */
        self::assertTrue(Json::decode('true', 'bool')); // @phpstan-ignore argument.type, staticMethod.impossibleType
        /** @psalm-suppress ArgumentTypeCoercion */
        self::assertNull(Json::decode('null', 'null')); // @phpstan-ignore argument.type, staticMethod.impossibleType
    }

    public function testDecodesRootClassUnionWithPrimitive(): void
    {
        $json = json_encode(['name' => 'Alice', 'age' => 30, 'score' => 95.5, 'active' => true], JSON_THROW_ON_ERROR);

        /** @psalm-suppress ArgumentTypeCoercion Type expressions are valid but not class-strings */
        $result = Json::decode($json, SimpleClass::class . '|null'); // @phpstan-ignore argument.type

        self::assertInstanceOf(SimpleClass::class, $result);
        self::assertSame('Alice', $result->name); // @phpstan-ignore class.notFound
    }

    public function testDecodesRootClassUnionWithNull(): void
    {
        /** @psalm-suppress ArgumentTypeCoercion Type expressions are valid but not class-strings */
        $result = Json::decode('null', SimpleClass::class . '|null'); // @phpstan-ignore argument.type

        self::assertNull($result); // @phpstan-ignore staticMethod.impossibleType
    }
}
