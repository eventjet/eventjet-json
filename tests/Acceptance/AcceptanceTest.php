<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
#[CoversClass(DecodeError::class)]
final class AcceptanceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[DataProvider('jsonValues')]
    public function testDecodeIsTheExactInverseOfJsonEncode(object $person): void
    {
        $json = json_encode($person, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $person::class);

        static::assertEquals($person, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    /** @param class-string $class */
    #[DataProvider('invalidJsonValues')]
    public function testDecodeReturnsErrorsAsValues(string $json, string $class, string $message, int $code): void
    {
        $decoded = Json::decode($json, $class);

        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame($message, $decoded->getMessage());
        static::assertSame($code, $decoded->getCode());
    }

    /** @return iterable<string, array{object}> */
    public static function jsonValues(): iterable
    {
        yield 'required properties' => [new Person('Ada', 'Lovelace')];
        yield 'middle name' => [new Person('John', 'Doe', 'Quincy')];
        yield 'age' => [new Person('Jane', 'Doe', age: 42)];
        yield 'all properties' => [new Person('Alice', 'Smith', 'Beth', 30)];

        foreach (self::stringFieldValues() as $name => [$value]) {
            yield $name => [new Person($value, $value, $value)];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function stringFieldValues(): iterable
    {
        yield 'Unicode' => ["Grüße, 世界, 😀, e\u{0301}"];
        yield 'escaped characters' => ["\"\\/\n\r\t\x08\x0c\0"];
        yield 'empty string' => [''];
        yield 'integer text' => ['42'];
        yield 'negative integer text' => ['-42'];
        yield 'decimal text' => ['3.14'];
        yield 'scientific notation text' => ['1e3'];
        yield 'leading zeros' => ['00042'];
        yield 'zero' => ['0'];
    }

    /** @return iterable<string, array{string, class-string, string, int}> */
    public static function invalidJsonValues(): iterable
    {
        yield 'malformed JSON' => ['{', Person::class, 'Invalid JSON: Syntax error', 1];
        yield 'JSON scalar' => ['null', Person::class, 'Expected the JSON root to be an object, got null.', 2];
        yield 'missing constructor arguments' => [
            '{}',
            Person::class,
            'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\Person from the JSON object: Too few arguments to function Eventjet\\Json\\Test\\Acceptance\\Fixtures\\Person::__construct(), 0 passed and at least 2 expected',
            3,
        ];
    }
}
