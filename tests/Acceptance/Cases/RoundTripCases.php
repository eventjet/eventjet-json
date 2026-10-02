<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Address;
use Eventjet\Json\Test\Acceptance\Fixtures\ArrayPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctStringEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\MapHolder;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedBackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\PublicPropertiesWithConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\RecursiveNode;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;

use function array_keys;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const PHP_FLOAT_EPSILON;
use const PHP_FLOAT_MAX;
use const PHP_FLOAT_MIN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class RoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{ArrayPublicProperty|BackedEnumFields|BackedEnumPublicProperties|ClassEnumScalarUnionField|ClassScalarUnionField|DisjointStringBackedEnumUnionField|DistinctEnumScalarUnionField|DistinctStringEnumScalarUnionField|Person|MapHolder|MultipleEnumUnionField|NestedBackedEnumFields|NestedObjectFields|NestedObjectPublicProperties|PublicPropertiesWithConstructor|RecursiveNode}>
     */
    public static function objects(): iterable
    {
        yield 'required properties' => [new Person('Ada', 'Lovelace')];
        yield 'middle name' => [new Person('John', 'Doe', 'Quincy')];
        yield 'age' => [new Person('Jane', 'Doe', age: 42)];
        yield 'all properties' => [new Person('Alice', 'Smith', 'Beth', 30)];

        $publicProperties = new PublicPropertiesWithConstructor('constructor value');
        $publicProperties->label = 'property value';
        $publicProperties->active = true;
        $publicProperties->inherited = 42;

        yield 'constructor-bound, direct, and inherited public properties' => [$publicProperties];

        $arrayPublicProperty = new ArrayPublicProperty();
        $arrayPublicProperty->value = ['items' => [['answer' => 42]]];

        yield 'array public property preserves nested objects and lists' => [$arrayPublicProperty];

        $backedEnumPublicProperties = new BackedEnumPublicProperties();
        $backedEnumPublicProperties->stringStatus = StringBackedStatus::Pending;
        $backedEnumPublicProperties->nullableStatus = StringBackedStatus::Ready;
        $backedEnumPublicProperties->intStatus = IntBackedStatus::Pending;

        yield 'backed enum public properties' => [$backedEnumPublicProperties];

        $backedEnumPublicPropertiesWithNull = new BackedEnumPublicProperties();
        $backedEnumPublicPropertiesWithNull->stringStatus = StringBackedStatus::Pending;
        $backedEnumPublicPropertiesWithNull->intStatus = IntBackedStatus::Pending;

        yield 'backed enum public properties with null' => [$backedEnumPublicPropertiesWithNull];

        $nestedPublicProperties = new NestedObjectPublicProperties();
        $nestedPublicProperties->person = new Person('Ada', 'Lovelace');
        $nestedPublicProperties->alternate = new Person('Charles', 'Babbage');
        $nestedPublicProperties->child = new NestedObjectPublicProperties();
        $nestedPublicProperties->child->person = new Person('Grace', 'Hopper');

        yield 'nested final class public properties' => [$nestedPublicProperties];

        $nullableNestedPublicProperty = new NestedObjectPublicProperties();
        $nullableNestedPublicProperty->person = new Person('Ada', 'Lovelace');

        yield 'nullable nested final class public property with null' => [$nullableNestedPublicProperty];
        yield 'array-valued field encoded as an object' => [new MapHolder(['answer' => 42])];
        yield 'array-valued field preserves nested objects and lists' => [
            new MapHolder(['items' => [['answer' => 42]]]),
        ];
        yield 'recursively nested readonly objects with a null field' => [
            new NestedObjectFields(
                new Person('Ada', 'Lovelace'),
                new Address('London', new Coordinates(51.507_351, -0.127_758)),
                null,
            ),
        ];
        yield 'recursively nested readonly objects with a non-null field' => [
            new NestedObjectFields(
                new Person('Ada', 'Lovelace'),
                new Address('Paris', new Coordinates(48.856_613, 2.352_222)),
                new Person('Charles', 'Babbage'),
            ),
        ];
        yield 'recursively nested self type' => [
            new RecursiveNode('root', new RecursiveNode('leaf', null)),
        ];
        yield 'class and scalar union with an object value' => [
            new ClassScalarUnionField(new Person('Ada', 'Lovelace')),
        ];
        yield 'class and scalar union with a string value' => [new ClassScalarUnionField('Ada')];
        yield 'class and scalar union with an integer value' => [new ClassScalarUnionField(42)];
        yield 'class and scalar union with a null value' => [new ClassScalarUnionField(null)];
        yield 'class, enum, and scalar union with an object value' => [
            new ClassEnumScalarUnionField(new Person('Ada', 'Lovelace')),
        ];
        yield 'class, enum, and scalar union with an enum value' => [
            new ClassEnumScalarUnionField(StringBackedStatus::Ready),
        ];
        yield 'class, enum, and scalar union with an integer value' => [new ClassEnumScalarUnionField(42)];
        yield 'class, enum, and scalar union with a null value' => [new ClassEnumScalarUnionField(null)];
        yield 'int-backed enum and string union with enum value Ready' => [
            new DistinctEnumScalarUnionField(IntBackedStatus::Ready),
        ];
        yield 'int-backed enum and string union with enum value Pending' => [
            new DistinctEnumScalarUnionField(IntBackedStatus::Pending),
        ];
        yield 'int-backed enum and string union with string value' => [
            new DistinctEnumScalarUnionField('ready'),
        ];
        yield 'int-backed enum and string union with numeric string' => [
            new DistinctEnumScalarUnionField('1'),
        ];
        yield 'string-backed enum and int union with enum value Ready' => [
            new DistinctStringEnumScalarUnionField(StringBackedStatus::Ready),
        ];
        yield 'string-backed enum and int union with enum value Pending' => [
            new DistinctStringEnumScalarUnionField(StringBackedStatus::Pending),
        ];
        yield 'string-backed enum and int union with integer value' => [
            new DistinctStringEnumScalarUnionField(42),
        ];
        yield 'multiple enums with different backing types select string-backed Ready' => [
            new MultipleEnumUnionField(StringBackedStatus::Ready),
        ];
        yield 'multiple enums with different backing types select string-backed Pending' => [
            new MultipleEnumUnionField(StringBackedStatus::Pending),
        ];
        yield 'multiple enums with different backing types select int-backed Ready' => [
            new MultipleEnumUnionField(IntBackedStatus::Ready),
        ];
        yield 'multiple enums with different backing types select int-backed Pending' => [
            new MultipleEnumUnionField(IntBackedStatus::Pending),
        ];
        yield 'multiple string-backed enums with disjoint values select status Ready' => [
            new DisjointStringBackedEnumUnionField(StringBackedStatus::Ready),
        ];
        yield 'multiple string-backed enums with disjoint values select status Pending' => [
            new DisjointStringBackedEnumUnionField(StringBackedStatus::Pending),
        ];
        yield 'multiple string-backed enums with disjoint values select outcome Complete' => [
            new DisjointStringBackedEnumUnionField(StringBackedOutcome::Complete),
        ];
        yield 'multiple string-backed enums with disjoint values select outcome Failed' => [
            new DisjointStringBackedEnumUnionField(StringBackedOutcome::Failed),
        ];

        yield 'backed enum fields: Ready, Ready' => [
            new BackedEnumFields(StringBackedStatus::Ready, null, IntBackedStatus::Ready),
        ];
        yield 'backed enum fields: Ready, Pending' => [
            new BackedEnumFields(StringBackedStatus::Ready, StringBackedStatus::Ready, IntBackedStatus::Pending),
        ];
        yield 'backed enum fields: Pending, Ready' => [
            new BackedEnumFields(StringBackedStatus::Pending, StringBackedStatus::Pending, IntBackedStatus::Ready),
        ];
        yield 'backed enum fields: Pending, Pending' => [
            new BackedEnumFields(StringBackedStatus::Pending, null, IntBackedStatus::Pending),
        ];
        yield 'nested backed enum fields with a nullable enum case' => [
            new NestedBackedEnumFields(new BackedEnumFields(
                StringBackedStatus::Ready,
                StringBackedStatus::Pending,
                IntBackedStatus::Ready,
            )),
        ];
        yield 'nested backed enum fields with a null enum field' => [
            new NestedBackedEnumFields(new BackedEnumFields(
                StringBackedStatus::Pending,
                null,
                IntBackedStatus::Pending,
            )),
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Person, string}>
     * @throws JsonException
     */
    public static function objectRootWhitespace(): iterable
    {
        $person = new Person('Ada', 'Lovelace');
        $json = json_encode($person, JSON_THROW_ON_ERROR);

        foreach ([
            'space' => ' ',
            'tab' => "\t",
            'carriage return' => "\r",
            'line feed' => "\n",
            'all' => " \t\r\n",
        ] as $name => $whitespace) {
            yield 'object root whitespace: ' . $name => [$person, $whitespace . $json . $whitespace];
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Person}>
     */
    public static function stringFields(): iterable
    {
        foreach ([
            'Unicode' => "Grüße, 世界, 😀, e\u{0301}",
            'escaped characters' => "\"\\/\n\r\t\x08\x0c\0",
            'empty string' => '',
            'integer text' => '42',
            'negative integer text' => '-42',
            'decimal text' => '3.14',
            'scientific notation text' => '1e3',
            'leading zeros' => '00042',
            'zero' => '0',
        ] as $name => $value) {
            yield $name => [new Person($value, $value, $value)];
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{ConstructorlessPublicProperties|LiteralBooleanFields|NullableScalarFields|ScalarFields}>
     */
    public static function scalarFields(): iterable
    {
        yield 'scalar fields with positive values' => [new ScalarFields('value', 42, 3.25, true)];
        yield 'scalar fields with a whole-valued float' => [new ScalarFields('value', 42, 3.0, true)];
        yield 'scalar fields with zero and false' => [new ScalarFields('', 0, 0.5, false)];
        yield 'nullable scalar fields with values' => [new NullableScalarFields('value', -42, -3.25, false, null)];
        yield 'nullable scalar fields set to null' => [
            new NullableScalarFields(null, null, null, null, null),
        ];
        yield 'literal boolean fields' => [new LiteralBooleanFields(true, false)];

        $constructorless = new ConstructorlessPublicProperties();
        $constructorless->string = 'value';
        $constructorless->integer = 42;
        $constructorless->float = 3.0;
        $constructorless->boolean = true;
        $constructorless->nullable = null;

        yield 'constructorless public scalar properties' => [$constructorless];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{ScalarFields}>
     */
    public static function numericBoundaries(): iterable
    {
        yield 'minimum platform integer' => [new ScalarFields('value', PHP_INT_MIN, 0.0, false)];
        yield 'maximum platform integer' => [new ScalarFields('value', PHP_INT_MAX, 0.0, false)];

        foreach ([
            'positive whole-valued float' => 3.0,
            'negative whole-valued float' => -3.0,
            'minimum positive normal float' => PHP_FLOAT_MIN,
            'minimum positive subnormal float' => PHP_FLOAT_MIN * PHP_FLOAT_EPSILON,
            'maximum finite float' => PHP_FLOAT_MAX,
            'negative maximum finite float' => -PHP_FLOAT_MAX,
            'precision-sensitive decimal' => 0.300_000_000_000_000_04,
            'first float above one' => 1.000_000_000_000_000_2,
            'largest consecutive integer float' => 9_007_199_254_740_991.0,
        ] as $name => $value) {
            yield $name => [new ScalarFields('value', 0, $value, false)];
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Person, string}>
     * @throws JsonException
     */
    public static function memberOrders(): iterable
    {
        $person = new Person('Ada', 'Lovelace', 'Byron', 36);
        $members = ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'middleName' => 'Byron', 'age' => 36];

        foreach (self::memberPermutations($members) as $permutation) {
            yield 'member order: ' . implode(', ', array_keys($permutation)) => [
                $person,
                json_encode($permutation, JSON_THROW_ON_ERROR),
            ];
        }
    }

    /**
     * @param array<string, string|int|null> $members
     * @return iterable<array<string, string|int|null>>
     */
    private static function memberPermutations(array $members): iterable
    {
        if ($members === []) {
            yield [];
            return;
        }

        foreach ($members as $name => $value) {
            $remaining = $members;
            unset($remaining[$name]);

            foreach (self::memberPermutations($remaining) as $permutation) {
                yield [$name => $value, ...$permutation];
            }
        }
    }
}
