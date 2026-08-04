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
use Eventjet\Test\Unit\Json\Fixtures\HasBoolProperty;
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
use Eventjet\Test\Unit\Json\Fixtures\TakesBool;
use Eventjet\Test\Unit\Json\Fixtures\TakesCallable;
use Eventjet\Test\Unit\Json\Fixtures\TakesFalse;
use Eventjet\Test\Unit\Json\Fixtures\TakesFloat;
use Eventjet\Test\Unit\Json\Fixtures\TakesInt;
use Eventjet\Test\Unit\Json\Fixtures\TakesIterable;
use Eventjet\Test\Unit\Json\Fixtures\TakesMapOrNull;
use Eventjet\Test\Unit\Json\Fixtures\TakesMixed;
use Eventjet\Test\Unit\Json\Fixtures\TakesMultilineList;
use Eventjet\Test\Unit\Json\Fixtures\TakesNonBackedEnum;
use Eventjet\Test\Unit\Json\Fixtures\TakesNull;
use Eventjet\Test\Unit\Json\Fixtures\TakesObject;
use Eventjet\Test\Unit\Json\Fixtures\TakesString;
use Eventjet\Test\Unit\Json\Fixtures\TakesStringStringMap;
use Eventjet\Test\Unit\Json\Fixtures\TakesTrue;
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
use function sprintf;

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
        yield 'Bool constructor argument' => [
            '{"value":true}',
            TakesBool::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesBool::class, $object);
                self::assertTrue($object->value);
            },
        ];
        yield 'Int constructor argument' => [
            '{"value":42}',
            TakesInt::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesInt::class, $object);
                self::assertSame(42, $object->value);
            },
        ];
        yield 'Float constructor argument' => [
            '{"value":1.5}',
            TakesFloat::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesFloat::class, $object);
                self::assertSame(1.5, $object->value);
            },
        ];
        yield 'String constructor argument' => [
            '{"value":"foo"}',
            TakesString::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesString::class, $object);
                self::assertSame('foo', $object->value);
            },
        ];
        yield 'Mixed constructor argument takes a string' => [
            '{"value":"foo"}',
            TakesMixed::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesMixed::class, $object);
                self::assertSame('foo', $object->value);
            },
        ];
        yield 'Mixed constructor argument takes a JSON object' => [
            '{"value":{"foo":"bar"}}',
            TakesMixed::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesMixed::class, $object);
                self::assertSame(['foo' => 'bar'], $object->value);
            },
        ];
        yield 'True constructor argument' => [
            '{"value":true}',
            TakesTrue::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesTrue::class, $object);
            },
        ];
        yield 'False constructor argument' => [
            '{"value":false}',
            TakesFalse::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesFalse::class, $object);
            },
        ];
        yield 'Null constructor argument' => [
            '{"value":null}',
            TakesNull::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesNull::class, $object);
            },
        ];
        yield 'Null for nullable constructor argument' => [
            '{"name":null}',
            NullableStringField::class,
            static function (object $object): void {
                self::assertInstanceOf(NullableStringField::class, $object);
                self::assertNull($object->name);
            },
        ];
        yield 'Omitted optional argument keeps its default and is not type checked' => [
            '{}',
            TakesIterable::class,
            static function (object $object): void {
                self::assertInstanceOf(TakesIterable::class, $object);
                self::assertSame([], $object->value);
            },
        ];
        yield 'Bool property' => [
            '{"value":true}',
            HasBoolProperty::class,
            static function (object $object): void {
                self::assertInstanceOf(HasBoolProperty::class, $object);
                self::assertTrue($object->value);
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
        yield 'String for bool constructor argument' => [
            '{"value":"not a boolean"}',
            TakesBool::class,
            'Expected bool for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesBool, got string',
        ];
        yield '"false" for bool constructor argument' => [
            '{"value":"false"}',
            TakesBool::class,
            'Expected bool for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesBool, got string',
        ];
        yield 'Zero for bool constructor argument' => [
            '{"value":0}',
            TakesBool::class,
            'Expected bool for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesBool, got integer',
        ];
        yield 'One for bool constructor argument' => [
            '{"value":1}',
            TakesBool::class,
            'Expected bool for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesBool, got integer',
        ];
        yield 'Fractional float for int constructor argument' => [
            '{"value":50.9}',
            TakesInt::class,
            'Expected int for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesInt, got double',
        ];
        yield 'Whole float for int constructor argument' => [
            '{"value":50.0}',
            TakesInt::class,
            'Expected int for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesInt, got double',
        ];
        yield 'Numeric string for int constructor argument' => [
            '{"value":"42"}',
            TakesInt::class,
            'Expected int for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesInt, got string',
        ];
        yield 'Bool for int constructor argument' => [
            '{"value":true}',
            TakesInt::class,
            'Expected int for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesInt, got boolean',
        ];
        yield 'Numeric string for float constructor argument' => [
            '{"value":"1.5"}',
            TakesFloat::class,
            'Expected float for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesFloat, got string',
        ];
        yield 'Bool for float constructor argument' => [
            '{"value":true}',
            TakesFloat::class,
            'Expected float for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesFloat, got boolean',
        ];
        yield 'Int for string constructor argument' => [
            '{"value":42}',
            TakesString::class,
            'Expected string for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesString, got integer',
        ];
        yield 'Float for string constructor argument' => [
            '{"value":1.5}',
            TakesString::class,
            'Expected string for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesString, got double',
        ];
        yield 'Bool for string constructor argument' => [
            '{"value":true}',
            TakesString::class,
            'Expected string for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesString, got boolean',
        ];
        yield 'False for true constructor argument' => [
            '{"value":false}',
            TakesTrue::class,
            'Expected true for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesTrue, got boolean',
        ];
        yield 'One for true constructor argument' => [
            '{"value":1}',
            TakesTrue::class,
            'Expected true for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesTrue, got integer',
        ];
        yield 'True for false constructor argument' => [
            '{"value":true}',
            TakesFalse::class,
            'Expected false for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesFalse, got boolean',
        ];
        yield 'Zero for false constructor argument' => [
            '{"value":0}',
            TakesFalse::class,
            'Expected false for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesFalse, got integer',
        ];
        yield 'String for null constructor argument' => [
            '{"value":"foo"}',
            TakesNull::class,
            'Expected null for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesNull, got string',
        ];
        yield 'Null for non-nullable constructor argument' => [
            '{"name":null}',
            StringField::class,
            'Expected string for parameter "name" of class Eventjet\Test\Unit\Json\Fixtures\StringField, got NULL',
        ];
        yield 'Iterable constructor argument' => [
            '{"value":[]}',
            TakesIterable::class,
            'Unsupported type "iterable" for parameter "value" of class '
            . 'Eventjet\Test\Unit\Json\Fixtures\TakesIterable',
        ];
        yield 'Object constructor argument' => [
            '{"value":{"foo":"bar"}}',
            TakesObject::class,
            'Unsupported type "object" for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesObject',
        ];
        yield 'Callable constructor argument' => [
            '{"value":"strlen"}',
            TakesCallable::class,
            'Unsupported type "callable" for parameter "value" of class '
            . 'Eventjet\Test\Unit\Json\Fixtures\TakesCallable',
        ];
        yield 'Type mismatch in a nested object' => [
            '{"nested":{"name":42}}',
            HasNestedClass::class,
            'Expected string for parameter "name" of class Eventjet\Test\Unit\Json\Fixtures\StringField, got integer',
        ];
        yield 'String for bool property' => [
            '{"value":"not a boolean"}',
            HasBoolProperty::class,
            'Expected bool for property "value" of class Eventjet\Test\Unit\Json\Fixtures\HasBoolProperty, got string',
        ];
        yield 'Int for nullable string property' => [
            '{"name":42}',
            new NullableStringField(),
            'Expected string for property "name" of class Eventjet\Test\Unit\Json\Fixtures\NullableStringField, got '
            . 'integer',
        ];
        yield 'String for array property' => [
            '{"topics":"foo"}',
            new Repository(),
            'Expected array for property "topics" of class Eventjet\Test\Unit\Json\Fixtures\GitHub\Repository, got '
            . 'string',
        ];
    }

    /**
     * @param class-string $class
     */
    private static function captureDecodeError(string $json, string $class): JsonError
    {
        try {
            Json::decode($json, $class);
        } catch (JsonError $error) {
            return $error;
        }
        self::fail(sprintf('Expected decoding %s into %s to fail, but it succeeded', $json, $class));
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

    /**
     * Widening an int to a float is the one conversion strict-mode parameter binding permits, and APIs routinely send
     * whole amounts without a fractional part.
     */
    public function testIntIsWidenedToFloatConstructorArgument(): void
    {
        $decoded = Json::decode('{"value":100}', TakesFloat::class);

        self::assertSame(100.0, $decoded->value);
    }

    public function testPromotedConstructorParametersAndPlainPropertiesRejectTheSameValue(): void
    {
        $viaConstructor = self::captureDecodeError('{"value":"not a boolean"}', TakesBool::class);
        $viaProperty = self::captureDecodeError('{"value":"not a boolean"}', HasBoolProperty::class);

        self::assertSame(
            'Expected bool for parameter "value" of class Eventjet\Test\Unit\Json\Fixtures\TakesBool, got string',
            $viaConstructor->getMessage(),
        );
        self::assertSame(
            'Expected bool for property "value" of class Eventjet\Test\Unit\Json\Fixtures\HasBoolProperty, got string',
            $viaProperty->getMessage(),
        );
    }
}
