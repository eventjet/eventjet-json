<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ArrayObjectMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields;
use JsonException;
use stdClass;

use function get_debug_type;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class ScalarMapErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach (self::invalidValues() as $name => [$field, $expectedType, $value]) {
            yield 'scalar map ' . $name => [
                json_encode([
                    'strings' => ['valid' => 'value'],
                    'integers' => ['valid' => 42],
                    'floats' => ['valid' => 3.25],
                    'booleans' => ['valid' => true],
                    $field => ['invalid' => $value],
                ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                ScalarMapFields::class,
                'Could not create '
                    . ScalarMapFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . '[invalid] must be of type '
                    . $expectedType
                    . ', '
                    . get_debug_type($value)
                    . ' given.',
                3,
            ];
        }

        yield 'public property scalar map rejects an invalid value' => [
            '{"strings":{"valid":"value"},"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true},"publicStrings":{"invalid":42}}',
            ScalarMapFields::class,
            'Could not create '
                . ScalarMapFields::class
                . ' from the JSON object: Field publicStrings[invalid] must be of type string, int given.',
            3,
        ];

        yield 'non-empty map rejects an empty JSON object' => [
            '{"strings":{},"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true}}',
            ScalarMapFields::class,
            'Could not create '
                . ScalarMapFields::class
                . ' from the JSON object: Field strings uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            3,
        ];

        yield 'non-empty map rejects a JSON array' => [
            '{"strings":["value"],"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true}}',
            ScalarMapFields::class,
            'Could not create '
                . ScalarMapFields::class
                . ' from the JSON object: Field strings must be of type JSON object, array given.',
            3,
        ];

        yield 'public property non-empty map rejects an empty JSON object' => [
            '{"strings":{"valid":"value"},"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true},"publicStrings":{}}',
            ScalarMapFields::class,
            'Could not create '
                . ScalarMapFields::class
                . ' from the JSON object: Field publicStrings uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            3,
        ];

        yield 'ArrayObject map rejects a JSON array' => [
            '{"strings":[],"people":{},"statuses":{}}',
            ArrayObjectMapFields::class,
            'Could not create '
                . ArrayObjectMapFields::class
                . ' from the JSON object: Field strings must be of type JSON object, array given.',
            3,
        ];

        yield 'ArrayObject scalar map rejects an invalid value' => [
            '{"strings":{"invalid":42},"people":{},"statuses":{}}',
            ArrayObjectMapFields::class,
            'Could not create '
                . ArrayObjectMapFields::class
                . ' from the JSON object: Field strings[invalid] must be of type string, int given.',
            3,
        ];

        yield 'non-empty map rejects a numeric-looking member name' => [
            '{"strings":{"0":"value"},"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true}}',
            ScalarMapFields::class,
            'Could not create '
                . ScalarMapFields::class
                . ' from the JSON object: Field strings has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            3,
        ];

        yield 'ArrayObject map rejects a numeric-looking member name' => [
            '{"strings":{"0":"value"},"people":{},"statuses":{}}',
            ArrayObjectMapFields::class,
            'Could not create '
                . ArrayObjectMapFields::class
                . ' from the JSON object: Field strings has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            3,
        ];
    }

    /** @return iterable<string, array{string, 'bool'|'float'|'int'|'string', array<array-key, mixed>|bool|float|int|object|string|null}> */
    private static function invalidValues(): iterable
    {
        $fields = [
            'strings' => ['string', [42, 3.25, true, null, [], new stdClass()]],
            'integers' => ['int', ['42', 42.0, true, null, [], new stdClass()]],
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
