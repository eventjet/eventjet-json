<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Json;
use Eventjet\Json\JsonError;
use Eventjet\Test\Unit\Json\Fixtures\AllSimpleTypes;
use Eventjet\Test\Unit\Json\Fixtures\IntBackedEnum;
use Eventjet\Test\Unit\Json\Fixtures\MultipleOptionalFields;
use Eventjet\Test\Unit\Json\Fixtures\NonBackedEnum;
use Eventjet\Test\Unit\Json\Fixtures\OptionalEnums;
use Eventjet\Test\Unit\Json\Fixtures\OptionalIntegerWithDefaultInt;
use Eventjet\Test\Unit\Json\Fixtures\OptionalMixed;
use Eventjet\Test\Unit\Json\Fixtures\OptionalStringDefaultNull;
use Eventjet\Test\Unit\Json\Fixtures\RequiredMixed;
use Eventjet\Test\Unit\Json\Fixtures\RequiredString;
use Eventjet\Test\Unit\Json\Fixtures\StringBackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class JsonTest extends TestCase
{
    /**
     * @return iterable<string, array{object}>
     */
    public static function provideRoundtripCases(): iterable
    {
        yield AllSimpleTypes::class => [new AllSimpleTypes('John', 42, 3.14, true, true, false, null)];
        yield IntBackedEnum::class => [new OptionalEnums(int: IntBackedEnum::Bar)];
        yield MultipleOptionalFields::class . ' with only second field' => [new MultipleOptionalFields(age: 42)];
        yield OptionalIntegerWithDefaultInt::class . ' with int' => [new OptionalIntegerWithDefaultInt(42)];
        yield OptionalIntegerWithDefaultInt::class . ' with default' => [new OptionalIntegerWithDefaultInt()];
        yield OptionalMixed::class . ' with string' => [new OptionalMixed('John')];
        yield OptionalStringDefaultNull::class . ' with string' => [new OptionalStringDefaultNull('John')];
        yield OptionalStringDefaultNull::class . ' with null' => [new OptionalStringDefaultNull(null)];
        yield OptionalStringDefaultNull::class . ' with default' => [new OptionalStringDefaultNull()];
        yield RequiredMixed::class . ' with string' => [new RequiredMixed('John')];
        yield RequiredMixed::class . ' with int' => [new RequiredMixed(123)];
        yield RequiredMixed::class . ' with float' => [new RequiredMixed(12.34)];
        yield RequiredMixed::class . ' with true' => [new RequiredMixed(true)];
        yield RequiredMixed::class . ' with false' => [new RequiredMixed(false)];
        yield RequiredMixed::class . ' with null' => [new RequiredMixed(null)];
        yield RequiredMixed::class . ' with string array' => [new RequiredMixed(['John', 'Jane'])];
        yield RequiredMixed::class . ' with int array' => [new RequiredMixed([1, 2, 3])];
        yield RequiredString::class => [new RequiredString('John')];
        yield StringBackedEnum::class => [new OptionalEnums(str: StringBackedEnum::Bar)];
    }

    /**
     * @return iterable<string, array{string, class-string, object}>
     */
    public static function provideDecodeCases(): iterable
    {
        yield 'Additional fields in the JSON are ignored' => [
            '{"name":"John"}',
            RequiredString::class,
            new RequiredString('John'),
        ];
        yield 'Missing optional field uses default' => [
            '{}',
            OptionalIntegerWithDefaultInt::class,
            new OptionalIntegerWithDefaultInt(),
        ];
        yield 'Non-first field missing' => [
            '{"age": 23}',
            MultipleOptionalFields::class,
            new MultipleOptionalFields(age: 23),
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function provideFailingDecodeCases(): iterable
    {
        yield 'Missing required field' => [
            '{}',
            RequiredString::class,
            sprintf('Missing required property "%s" in JSON data for class %s.', 'name', RequiredString::class),
        ];
        yield 'Wrong type for field' => [
            '{"name": 123}',
            RequiredString::class,
            'Field "name" expected to be of type string, got int.',
        ];
        yield 'Explicit null for non-nullable optional field' => [
            '{"age": null}',
            OptionalIntegerWithDefaultInt::class,
            'Field "age" expected to be of type int, got null.',
        ];
        yield 'JSON object for required mixed property' => [
            '{"val": {"foo": "bar"}}',
            RequiredMixed::class,
            sprintf(
                'To populate a property with an object, it must have a specific class type instead of mixed (property "val" in class %s).',
                RequiredMixed::class,
            ),
        ];
        yield 'JSON object for optional mixed property' => [
            '{"val": {"foo": "bar"}}',
            OptionalMixed::class,
            sprintf(
                'To populate a property with an object, it must have a specific class type instead of mixed (property "val" in class %s).',
                OptionalMixed::class,
            ),
        ];
        yield 'JSON object for optional string property' => [
            '{"name": {"foo": "bar"}}',
            OptionalStringDefaultNull::class,
            'Can\'t populate property "name" of type string with JSON object.',
        ];
        yield 'Non-existent string-backed enum case' => [
            '{"str": "baz"}',
            OptionalEnums::class,
            sprintf('"baz" is not a valid a value of any case of enum %s.', StringBackedEnum::class),
        ];
        yield 'Non-existent int-backed enum case' => [
            '{"int": 420}',
            OptionalEnums::class,
            sprintf('420 is not a valid a value of any case of enum %s.', IntBackedEnum::class),
        ];
        yield 'Value for non-backed enum' => [
            '{"nb": "Foo"}',
            OptionalEnums::class,
            sprintf('All enums must be backed, but %s is not a backed enum.', NonBackedEnum::class),
        ];
        yield 'Int for string-backed enum' => [
            '{"str": 123}',
            OptionalEnums::class,
            sprintf('123 is not a valid a value of any case of enum %s.', StringBackedEnum::class),
        ];
        yield 'String for int-backed enum' => [
            '{"int": "foo"}',
            OptionalEnums::class,
            sprintf('"foo" is not a valid a value of any case of enum %s.', IntBackedEnum::class),
        ];
        yield 'true for string-backed enum' => [
            '{"str": true}',
            OptionalEnums::class,
            sprintf('true is not a valid a value of any case of enum %s.', StringBackedEnum::class),
        ];
    }

    #[DataProvider('provideRoundtripCases')]
    public function testRoundtrip(object $object): void
    {
        $json = json_encode($object, JSON_THROW_ON_ERROR);
        $decoded = Json::decode($json, $object::class);

        self::assertEquals($object, $decoded);
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideDecodeCases')]
    public function testDecode(string $json, string $class, object $expected): void
    {
        $decoded = Json::decode($json, $class);

        self::assertEquals($expected, $decoded);
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideFailingDecodeCases')]
    public function testFailingDecode(string $json, string $class, string $expectedMessage): void
    {
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage($expectedMessage);

        Json::decode($json, $class);
    }
}
