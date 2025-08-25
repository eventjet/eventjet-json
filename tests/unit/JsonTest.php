<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use DateTimeInterface;
use DoesNotExist;
use Eventjet\Json\Json;
use Eventjet\Json\JsonError;
use Eventjet\Test\Unit\Json\Fixtures\ConstructorTakesAnUnknownClass;
use Eventjet\Test\Unit\Json\Fixtures\GitHub\Repository;
use Eventjet\Test\Unit\Json\Fixtures\GitHub\RepositoryOwner;
use Eventjet\Test\Unit\Json\Fixtures\HasImportedListItemType;
use Eventjet\Test\Unit\Json\Fixtures\HasIntersectionType;
use Eventjet\Test\Unit\Json\Fixtures\HasListOfStrings;
use Eventjet\Test\Unit\Json\Fixtures\HasMapOfObjects;
use Eventjet\Test\Unit\Json\Fixtures\HasNestedClass;
use Eventjet\Test\Unit\Json\Fixtures\HasUnionType;
use Eventjet\Test\Unit\Json\Fixtures\InvalidArrayConstructorParamTag;
use Eventjet\Test\Unit\Json\Fixtures\NullableStringField;
use Eventjet\Test\Unit\Json\Fixtures\Person;
use Eventjet\Test\Unit\Json\Fixtures\PersonList;
use Eventjet\Test\Unit\Json\Fixtures\PromotedPropertyWithMissingType;
use Eventjet\Test\Unit\Json\Fixtures\SomePropertiesAreNotConstructorArguments;
use Eventjet\Test\Unit\Json\Fixtures\StringField;
use Eventjet\Test\Unit\Json\Fixtures\TakesAListOfDateTimes;
use Eventjet\Test\Unit\Json\Fixtures\TakesMapOrNull;
use Eventjet\Test\Unit\Json\Fixtures\TakesMultilineList;
use Eventjet\Test\Unit\Json\Fixtures\TakesNonBackedEnum;
use Eventjet\Test\Unit\Json\Fixtures\TakesStringStringMap;
use Eventjet\Test\Unit\Json\Fixtures\UndocumentedListItemType;
use Eventjet\Test\Unit\Json\Fixtures\UndocumentedListItemTypeNoDocblock;
use Eventjet\Test\Unit\Json\Fixtures\UndocumentedMap;
use Eventjet\Test\Unit\Json\Fixtures\Worldline\AccountOnFile;
use Eventjet\Test\Unit\Json\Fixtures\Worldline\AccountOnFileAttribute;
use Eventjet\Test\Unit\Json\Fixtures\Worldline\AccountOnFileAttributeMustWriteReason;
use Eventjet\Test\Unit\Json\Fixtures\Worldline\AccountOnFileAttributeStatus;
use Eventjet\Test\Unit\Json\Fixtures\Worldline\AccountOnFileDisplayHints;
use Eventjet\Test\Unit\Json\Fixtures\Worldline\LabelTemplateElement;
use Eventjet\Test\Unit\Json\Fixtures\WrongArrayDocblockType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThisClassDoesNotExist;

use function assert;
use function file_get_contents;
use function fopen;
use function get_class;

final class JsonTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function encodeCases(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'int' => [42, '42'];
        yield 'float' => [3.14, '3.14'];
        yield 'string' => ['foo', '"foo"'];
        yield 'int array' => [[1, 2, 3], '[1,2,3]'];
        yield 'string array' => [['foo', 'bar'], '["foo","bar"]'];
        yield 'struct with string field' => [
            new class ('myvalue') {
                public function __construct(public string $foo)
                {
                }
            },
            '{"foo":"myvalue"}',
        ];
        yield 'struct with nullable string field' => [
            new class ('myvalue') {
                public function __construct(public string|null $foo)
                {
                }
            },
            '{"foo":"myvalue"}',
        ];
        yield 'array<string, string> in class field' => [
            new class {
                /**
                 * @param array<string, string> $foo
                 */
                public function __construct(public array $foo = ['f1' => 'foo', 'f2' => 'bar'])
                {
                }
            },
            '{"foo":{"f1":"foo","f2":"bar"}}',
        ];
        yield 'Object with multiple fields' => [
            new Person('John Doe', 42),
            '{"full_name":"John Doe","age":42}',
        ];
        yield 'Nested object' => [
            new HasNestedClass(new StringField('myvalue')),
            '{"nested":{"name":"myvalue"}}',
        ];
        yield 'Array field with object items' => [
            new PersonList([new Person('John Doe', 42), new Person('Jane Doe', 42)]),
            '{"people":[{"full_name":"John Doe","age":42},{"full_name":"Jane Doe","age":42}]}',
        ];
    }

    /**
     * @return iterable<string, array{string, object | class-string, callable(object): void}>
     */
    public static function decodeCases(): iterable
    {
        yield 'Struct with string field' => [
            '{"name":"Joe"}',
            new StringField(),
            static function (object $object): void {
                self::assertInstanceOf(StringField::class, $object);
                self::assertSame('Joe', $object->name);
            },
        ];
        yield 'Struct with nullable string field' => [
            '{"name":null}',
            new NullableStringField('Joe'),
            static function (object $object): void {
                self::assertInstanceOf(NullableStringField::class, $object);
                self::assertNull($object->name);
            },
        ];
        yield 'Nested struct' => [
            '{"nested":{"name":"Joe"}}',
            new HasNestedClass(),
            static function (object $object): void {
                self::assertInstanceOf(HasNestedClass::class, $object);
                self::assertSame('Joe', $object->nested?->name);
            },
        ];
        yield 'Nested struct with null value' => [
            '{"nested":null}',
            new HasNestedClass(new StringField()),
            static function (object $object): void {
                self::assertInstanceOf(HasNestedClass::class, $object);
                self::assertNull($object->nested);
            },
        ];
        $repositoryJson = file_get_contents(__DIR__ . '/Fixtures/GitHub/repository.json');
        assert($repositoryJson !== false);
        yield 'GitHub repository response' => [
            $repositoryJson,
            new Repository(),
            static function (object $object): void {
                self::assertInstanceOf(Repository::class, $object);
                self::assertSame(1296269, $object->id);
                self::assertSame('MDEwOlJlcG9zaXRvcnkxMjk2MjY5', $object->nodeId);
                self::assertSame('Hello-World', $object->name);
                self::assertSame('octocat/Hello-World', $object->fullName);
                self::assertSame('octocat', $object->owner->login);
                self::assertSame(1, $object->owner->id);
                self::assertSame('MDQ6VXNlcjE=', $object->owner->nodeId);
                self::assertFalse($object->private);
                self::assertNull($object->language);
                self::assertSame(['octocat', 'atom', 'electron', 'api'], $object->topics);
            },
        ];
        yield 'Array field with objects' => [
            '{"people":[{"full_name":"John Doe","age":42},{"full_name":"Jane Doe","age":69}]}',
            new PersonList(),
            static function (object $object): void {
                self::assertInstanceOf(PersonList::class, $object);
                self::assertCount(2, $object->people);
                self::assertSame('John Doe', $object->people[0]->fullName);
                self::assertSame(42, $object->people[0]->age);
                self::assertSame('Jane Doe', $object->people[1]->fullName);
                self::assertSame(69, $object->people[1]->age);
            },
        ];
        yield 'By class name' => [
            <<<'JSON'
                {
                    "attributes": [{
                        "key": "myattr",
                        "mustWriteReason": "IN_THE_PAST",
                        "status": "MUST_WRITE",
                        "value": "myval"
                    }],
                    "displayHints": {"labelTemplate": [{"attributeKey": "mykey", "mask": "mymask"}], "logo": "mylogo"},
                    "id": 123
                }
                JSON,
            AccountOnFile::class,
            static function (object $object): void {
                self::assertInstanceOf(AccountOnFile::class, $object);

                self::assertNotNull($object->attributes);
                self::assertNotNull($object->displayHints);
                self::assertSame(123, $object->id);
                self::assertNull($object->paymentProductId);

                self::assertCount(1, $object->attributes);
                self::assertSame('myattr', $object->attributes[0]->key);
                self::assertSame(
                    AccountOnFileAttributeMustWriteReason::InThePast,
                    $object->attributes[0]->mustWriteReason,
                );
                self::assertSame(AccountOnFileAttributeStatus::MustWrite, $object->attributes[0]->status);
                self::assertSame('myval', $object->attributes[0]->value);

                self::assertNotNull($object->displayHints->labelTemplate);
                self::assertSame('mylogo', $object->displayHints->logo);

                self::assertCount(1, $object->displayHints->labelTemplate);
                self::assertSame('mykey', $object->displayHints->labelTemplate[0]->attributeKey);
                self::assertSame('mymask', $object->displayHints->labelTemplate[0]->mask);
            },
        ];
        yield 'List constructor arguments with imported item type' => [
            '{"items1":[{"name":"foo"}],"items2":[{"name":"bar"}],"items3":[{"name":"baz"}]}',
            HasImportedListItemType::class,
            static function (object $object): void {
                self::assertInstanceOf(HasImportedListItemType::class, $object);
                self::assertCount(1, $object->items1);
                self::assertSame('foo', $object->items1[0]->name);
                self::assertCount(1, $object->items2);
                self::assertSame('bar', $object->items2[0]->name);
                self::assertCount(1, $object->items3);
                self::assertSame('baz', $object->items3[0]->name);
            },
        ];
        yield 'Invalid @param tags are skipped' => [
            '{"items":[{"datetime":"2023-01-01T00:00:00+00:00"}]}',
            InvalidArrayConstructorParamTag::class,
            static function (object $object): void {
                self::assertInstanceOf(InvalidArrayConstructorParamTag::class, $object);
                self::assertCount(1, $object->items);
            },
        ];
        yield 'Constructor takes a list of strings' => [
            '{"tags":["foo","bar"]}',
            HasListOfStrings::class,
            static function (object $object): void {
                self::assertInstanceOf(HasListOfStrings::class, $object);
                self::assertCount(2, $object->tags);
                self::assertSame('foo', $object->tags[0]);
                self::assertSame('bar', $object->tags[1]);
            },
        ];
        yield 'Populates non-constructor properties' => [
            '{"name":"Joe","age":42}',
            SomePropertiesAreNotConstructorArguments::class,
            static function (object $object): void {
                self::assertInstanceOf(SomePropertiesAreNotConstructorArguments::class, $object);
                self::assertSame('Joe', $object->name);
                self::assertSame(42, $object->age);
            },
        ];
        yield 'Array of built-in classes' => [
            '{"dates":[{"datetime":"2023-01-01T00:00:00+00:00"},{"datetime":"2023-01-02T00:00:00+00:00"}]}',
            TakesAListOfDateTimes::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesAListOfDateTimes::class, $object);
                self::assertCount(2, $object->dates);
                self::assertSame('2023-01-01T00:00:00+00:00', $object->dates[0]->format(DateTimeInterface::ATOM));
                self::assertSame('2023-01-02T00:00:00+00:00', $object->dates[1]->format(DateTimeInterface::ATOM));
            },
        ];
        yield 'HandlesTypesSpanningMultipleLines' => [
            '{"items":["foo"]}',
            TakesMultilineList::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesMultilineList::class, $object);
                self::assertCount(1, $object->items);
                self::assertSame('foo', $object->items[0]);
            },
        ];
        yield 'Map of objects' => [
            '{"map":{"foo":{"name":"Foo"},"bar":{"name":"Bar"}}}',
            HasMapOfObjects::class,
            static function (object $object): void {
                self::assertInstanceOf(HasMapOfObjects::class, $object);
                self::assertCount(2, $object->map);
                self::assertArrayHasKey('foo', $object->map);
                self::assertArrayHasKey('bar', $object->map);
                self::assertSame('Foo', $object->map['foo']->name);
                self::assertSame('Bar', $object->map['bar']->name);
            },
        ];
        yield 'String-string map' => [
            '{"map":{"foo":"Foo","bar":"Bar"}}',
            TakesStringStringMap::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesStringStringMap::class, $object);
                self::assertCount(2, $object->map);
                self::assertArrayHasKey('foo', $object->map);
                self::assertArrayHasKey('bar', $object->map);
                self::assertSame('Foo', $object->map['foo']);
                self::assertSame('Bar', $object->map['bar']);
            },
        ];
        yield 'Null for nullable map' => [
            '{"map":null}',
            TakesMapOrNull::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesMapOrNull::class, $object);
                self::assertNull($object->map);
            },
        ];
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function roundtripsCases(): iterable
    {
        yield 'GitHub repository response' => [
            new Repository(
                id: 1296269,
                nodeId: 'MDEwOlJlcG9zaXRvcnkxMjk2MjY5',
                name: 'Hello-World',
                fullName: 'octocat/Hello-World',
                owner: new RepositoryOwner(
                    name: 'monalisa octocat',
                    email: null,
                    login: 'octocat',
                    id: 1,
                    nodeId: 'MDQ6VXNlcjE=',
                ),
                private: false,
                language: null,
                topics: ['octocat', 'atom', 'electron', 'api'],
            ),
        ];
        yield 'Worldline AccountOnFile with no data' => [new AccountOnFile()];
        yield 'Worldline AccountOnFile with maximum data' => [
            new AccountOnFile(
                attributes: [
                    new AccountOnFileAttribute(
                        key: 'myattr',
                        mustWriteReason: AccountOnFileAttributeMustWriteReason::InThePast,
                        status: AccountOnFileAttributeStatus::MustWrite,
                        value: 'myval',
                    ),
                    new AccountOnFileAttribute(
                        key: 'myattr2',
                        mustWriteReason: AccountOnFileAttributeMustWriteReason::InThePast,
                        status: AccountOnFileAttributeStatus::MustWrite,
                        value: 'myval2',
                    ),
                ],
                displayHints: new AccountOnFileDisplayHints(
                    labelTemplate: [
                        new LabelTemplateElement(
                            attributeKey: 'mykey',
                            mask: 'mymask',
                        ),
                        new LabelTemplateElement(
                            attributeKey: 'mykey2',
                            mask: 'mymask2',
                        ),
                    ],
                ),
                id: 69,
                paymentProductId: 123,
            ),
        ];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function failingEncodeCases(): iterable
    {
        yield 'Resource' => [fopen('php://memory', 'r')];
        yield 'Resource in array' => [[fopen('php://memory', 'r')]];
    }

    /**
     * @return iterable<string, array{0: string, 1: object | class-string, 2?: string}>
     */
    public static function failingDecodeCases(): iterable
    {
        yield 'Invalid JSON' => ['{', new StringField(), 'JSON decoding failed'];
        yield 'Union field' => [
            '{"nested":{"name":"Test"}}',
            new class {
                public function __construct(public StringField|NullableStringField|null $nested = null)
                {
                }
            },
            'Property "nested" has a union or intersection type (Eventjet\Test\Unit\Json\Fixtures\StringField|'
            . 'Eventjet\Test\Unit\Json\Fixtures\NullableStringField|null), but only simple types are allowed',
        ];
        yield 'Missing field type' => [
            '{"nested":{"name":"Test"}}',
            new PromotedPropertyWithMissingType(),
            'Property "nested" has no type',
        ];
        yield 'Expected object, got string' => [
            '"mystring"',
            new StringField(),
            'Expected JSON object, got string',
        ];
        yield 'Expected object, got array' => [
            '["John Doe"]',
            new StringField(),
            'Expected JSON object, got array',
        ];
        yield 'Unknown field type' => [
            '{"nested":{"name":"Test"}}',
            new class {
                /**
                 * @phpstan-ignore-next-line
                 */
                public function __construct(public DoesNotExist|null $nested = null)
                {
                }
            },
            'Property "nested" has an unknown type "DoesNotExist"',
        ];
        yield 'String for class item in array property' => [
            '{"people":["Test"]}',
            new PersonList(),
            'Expected JSON objects for items in property "people", got string',
        ];
        yield 'Wrong dockblock type for array constructor parameter' => [
            '{"items":[{"datetime":"2023-01-23T12:34:56+00:00"}]}',
            WrongArrayDocblockType::class,
            'The doc type for the constructor argument items of '
            . 'Eventjet\Test\Unit\Json\Fixtures\WrongArrayDocblockType is wrong. Expected "list<...>", got '
            . '"class-string<DateTimeImmutable>"',
        ];
        yield 'Constructor param has intersection type' => [
            '{"value":{"foo":"bar"}}',
            HasIntersectionType::class,
            'Intersection types are not supported',
        ];
        // We might be able to support union types later
        yield 'Constructor param has union type' => [
            '{"value":{"datetime":"2023-01-23T12:34:56+00:00"}}',
            HasUnionType::class,
            'Union types are not supported',
        ];
        yield 'Non-array value for object constructor type' => [
            '{"displayHints":"not-an-object"}',
            AccountOnFile::class,
            'Expected array<string, mixed> for parameter "displayHints", got string',
        ];
        yield 'Invalid enum case value' => [
            '{"status":"NOPE"}',
            AccountOnFileAttribute::class,
            '"NOPE" is not a valid value for enum'
            . ' Eventjet\Test\Unit\Json\Fixtures\Worldline\AccountOnFileAttributeStatus. Valid values are: READ_ONLY,'
            . ' CAN_WRITE, MUST_WRITE',
        ];
        yield 'Float value for enum case' => [
            '{"status":1.0}',
            AccountOnFileAttribute::class,
            'Expected string or int for parameter "status", got double',
        ];
        yield 'string item for list constructor parameter with object items' => [
            '{"attributes":["foo"]}',
            AccountOnFile::class,
            'Expected JSON objects for items in property "attributes", got string',
        ];
        yield 'Undocumented item type for list constructor parameter' => [
            '{"items":[{"test":"foo"}]}',
            UndocumentedListItemType::class,
            'The type of the constructor parameter "items" for class'
            . ' Eventjet\Test\Unit\Json\Fixtures\UndocumentedListItemType is "array", but its shape is not documented',
        ];
        yield 'Undocumented item type for list constructor parameter (no docblock)' => [
            '{"items":[{"test":"foo"}]}',
            UndocumentedListItemTypeNoDocblock::class,
            'The type of the constructor parameter "items" for class'
            . ' Eventjet\Test\Unit\Json\Fixtures\UndocumentedListItemTypeNoDocblock is "array", but its shape is not'
            . ' documented',
        ];
        yield 'String for constructor argument that takes an array' => [
            '{"tags":"foo"}',
            HasListOfStrings::class,
            'Expected array for parameter "tags", got string',
        ];
        yield 'Constructor takes an unknown class' => [
            '{"foo":{"bar":"baz"}}',
            ConstructorTakesAnUnknownClass::class,
            'The type of the constructor parameter "foo" for class '
            . 'Eventjet\Test\Unit\Json\Fixtures\ConstructorTakesAnUnknownClass is "DoesNotExist", but this class does '
            . 'not exist',
        ];
        yield 'Missing required constructor argument' => [
            '{}',
            HasListOfStrings::class,
            'Missing required constructor argument "tags"',
        ];
        /** @psalm-suppress UndefinedClass */
        yield 'Class does not exist' => [
            '{}',
            ThisClassDoesNotExist::class, // @phpstan-ignore-line
            'Class "ThisClassDoesNotExist" does not exist',
        ];
        yield 'Non-backed enum' => [
            '{"status":"Enabled"}',
            TakesNonBackedEnum::class,
            'Only backed enums are allowed as constructor arguments, but '
            . '"Eventjet\Test\Unit\Json\Fixtures\NonBackedEnum" is not backed',
        ];
        yield 'JSON object for constructor argument that takes a list' => [
            '{"tags":{"foo":"bar"}}',
            HasListOfStrings::class,
            'The type of the constructor parameter "tags" for class Eventjet\Test\Unit\Json\Fixtures\HasListOfStrings '
            . 'is wrong. Expected "array<K, V>", got "list<string>"',
        ];
        yield 'String for map value expecting objects' => [
            '{"map":{"foo":"bar"}}',
            HasMapOfObjects::class,
            'Expected an array for the value of key "foo" in parameter "map", got string',
        ];
        yield 'Undocumented map' => [
            '{"map":{"foo":{"bar":"baz"}}}',
            UndocumentedMap::class,
            'The type of the constructor parameter "map" for class Eventjet\Test\Unit\Json\Fixtures\UndocumentedMap is '
            . '"array", but its shape is not documented',
        ];
    }

    #[DataProvider('encodeCases')]
    public function testEncode(mixed $value, string $expected): void
    {
        $encoded = Json::encode($value);

        self::assertSame($expected, $encoded);
    }

    /**
     * @param object | class-string $object
     * @param callable(object): void $test
     */
    #[DataProvider('decodeCases')]
    public function testDecode(string $json, object|string $object, callable $test): void
    {
        $object = Json::decode($json, $object);

        $test($object);
    }

    #[DataProvider('roundtripsCases')]
    public function testRoundtrips(object $value): void
    {
        $encoded1 = Json::encode($value);
        Json::decode($encoded1, get_class($value));
        $encoded2 = Json::encode($value);

        self::assertJsonStringEqualsJsonString($encoded1, $encoded2);
    }

    #[DataProvider('failingEncodeCases')]
    public function testFailingEncode(mixed $value): void
    {
        $this->expectException(JsonError::class);
        $this->expectExceptionCode(0);

        Json::encode($value);
    }

    /**
     * @param object | class-string $object
     */
    #[DataProvider('failingDecodeCases')]
    public function testFailingDecode(string $json, object|string $object, string|null $expectedMessage = null): void
    {
        $this->expectException(JsonError::class);
        $this->expectExceptionCode(0);
        if ($expectedMessage !== null) {
            $this->expectExceptionMessage($expectedMessage);
        }

        Json::decode($json, $object);
    }
}
