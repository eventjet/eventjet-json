<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\MapHolder;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use JsonException;

use function array_keys;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class RoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Person|MapHolder}>
     */
    public static function objects(): iterable
    {
        yield 'required properties' => [new Person('Ada', 'Lovelace')];
        yield 'middle name' => [new Person('John', 'Doe', 'Quincy')];
        yield 'age' => [new Person('Jane', 'Doe', age: 42)];
        yield 'all properties' => [new Person('Alice', 'Smith', 'Beth', 30)];
        yield 'array-valued field encoded as an object' => [new MapHolder(['answer' => 42])];
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
     * @return iterable<string, array{LiteralBooleanFields|NullableScalarFields|ScalarFields}>
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
