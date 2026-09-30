<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use UnexpectedValueException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
final class AcceptanceTest extends TestCase
{
    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    #[DataProvider('jsonValues')]
    public function testDecodeIsTheExactInverseOfJsonEncode(object $person): void
    {
        $json = json_encode($person, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $person::class);

        static::assertEquals($person, $decoded);
    }

    /** @return iterable<string, array{object}> */
    public static function jsonValues(): iterable
    {
        yield 'required properties' => [new Person('Ada', 'Lovelace')];
        yield 'middle name' => [new Person('John', 'Doe', 'Quincy')];
        yield 'age' => [new Person('Jane', 'Doe', age: 42)];
        yield 'all properties' => [new Person('Alice', 'Smith', 'Beth', 30)];
    }
}
