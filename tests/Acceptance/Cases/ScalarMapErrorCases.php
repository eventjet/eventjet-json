<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

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
                    'strings' => [],
                    'integers' => [],
                    'floats' => [],
                    'booleans' => [],
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
            '{"strings":{},"integers":{},"floats":{},"booleans":{},"publicStrings":{"invalid":42}}',
            ScalarMapFields::class,
            'Could not create '
                . ScalarMapFields::class
                . ' from the JSON object: Field publicStrings[invalid] must be of type string, int given.',
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
