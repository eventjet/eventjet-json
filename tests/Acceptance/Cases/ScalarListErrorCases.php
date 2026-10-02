<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListPublicProperty;
use JsonException;
use stdClass;

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
