<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonEmptyListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListPublicProperty;
use JsonException;
use stdClass;

use function array_fill;
use function get_debug_type;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class ScalarListErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach (self::invalidItems() as $name => [$field, $expectedType, $value]) {
            yield $name => [
                json_encode([
                    'strings' => [],
                    'stringsExtra' => [],
                    'floats' => [],
                    'booleans' => [],
                    $field => [$value],
                ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                ScalarListFields::class,
                'Could not create '
                    . ScalarListFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . '[0] must be of type '
                    . $expectedType
                    . ', '
                    . get_debug_type($value)
                    . ' given.',
                3,
            ];
        }

        foreach (self::invalidItems() as $name => [$field, $expectedType, $value]) {
            $valid = match ($expectedType) {
                'string' => 'valid',
                'int' => 42,
                'float' => 1.5,
                'bool' => false,
            };
            yield $name . ' after 1000 valid items' => [
                json_encode([
                    'strings' => [],
                    'stringsExtra' => [],
                    'floats' => [],
                    'booleans' => [],
                    $field => [...array_fill(0, count: 1000, value: $valid), $value],
                ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                ScalarListFields::class,
                'Could not create '
                    . ScalarListFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . '[1000] must be of type '
                    . $expectedType
                    . ', '
                    . get_debug_type($value)
                    . ' given.',
                3,
            ];
        }

        foreach ([
            'empty object' => new stdClass(),
            'object with numeric-looking keys' => (object) ['0' => 'value'],
        ] as $name => $value) {
            yield 'string list rejects ' . $name => [
                json_encode([
                    'strings' => $value,
                    'stringsExtra' => [],
                    'floats' => [],
                    'booleans' => [],
                ], JSON_THROW_ON_ERROR),
                ScalarListFields::class,
                'Could not create '
                    . ScalarListFields::class
                    . ' from the JSON object: Field strings must be of type list<string>, stdClass given.',
                3,
            ];
        }

        yield 'public property scalar list rejects an invalid item' => [
            '{"values":[42]}',
            ScalarListPublicProperty::class,
            'Could not create '
                . ScalarListPublicProperty::class
                . ' from the JSON object: Field values[0] must be of type string, int given.',
            3,
        ];

        yield 'scalar list reports a nonzero failing index' => [
            '{"strings":["valid",42],"stringsExtra":[],"floats":[],"booleans":[]}',
            ScalarListFields::class,
            'Could not create '
                . ScalarListFields::class
                . ' from the JSON object: Field strings[1] must be of type string, int given.',
            3,
        ];

        yield 'non-empty constructor list rejects an empty list' => [
            '{"values":[]}',
            NonEmptyListFields::class,
            'Could not create '
                . NonEmptyListFields::class
                . ' from the JSON object: Field values must be of type non-empty-list<int>, empty list given.',
            3,
        ];

        yield 'non-empty public property list rejects an empty list' => [
            '{"values":[1],"labels":[]}',
            NonEmptyListFields::class,
            'Could not create '
                . NonEmptyListFields::class
                . ' from the JSON object: Field labels must be of type non-empty-list<string>, empty list given.',
            3,
        ];

        yield 'non-empty constructor list validates each item' => [
            '{"values":[1,"2"]}',
            NonEmptyListFields::class,
            'Could not create '
                . NonEmptyListFields::class
                . ' from the JSON object: Field values[1] must be of type int, string given.',
            3,
        ];

        yield 'non-empty public property list validates each item' => [
            '{"values":[1],"labels":["valid",2]}',
            NonEmptyListFields::class,
            'Could not create '
                . NonEmptyListFields::class
                . ' from the JSON object: Field labels[1] must be of type string, int given.',
            3,
        ];

        yield 'non-empty list rejects a JSON object' => [
            '{"values":{}}',
            NonEmptyListFields::class,
            'Could not create '
                . NonEmptyListFields::class
                . ' from the JSON object: Field values must be of type non-empty-list<int>, stdClass given.',
            3,
        ];
    }

    /** @return iterable<string, array{string, 'bool'|'float'|'int'|'string', array<array-key, mixed>|bool|float|int|object|string|null}> */
    private static function invalidItems(): iterable
    {
        $fields = [
            'strings' => ['string', [42, 3.25, true, null, [], new stdClass()]],
            'stringsExtra' => ['int', ['42', 42.0, true, null, [], new stdClass()]],
            'floats' => ['float', ['3.25', true, null, [], new stdClass()]],
            'booleans' => ['bool', ['true', 1, 1.0, null, [], new stdClass()]],
        ];

        foreach ($fields as $field => [$expectedType, $values]) {
            foreach ($values as $value) {
                yield $field . ' rejects ' . get_debug_type($value) => [$field, $expectedType, $value];
            }
        }
    }
}
