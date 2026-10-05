<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntegerRangeCollectionFields;
use JsonException;
use stdClass;

use function get_debug_type;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class IntegerRangeCollectionCases
{
    /** @return iterable<string, array{IntegerRangeCollectionFields}> */
    public static function objects(): iterable
    {
        yield 'integer range collections preserve empty lists and maps' => [new IntegerRangeCollectionFields()];

        /** @var list<array{int<5, max>, int<min, -1>}> $bounds */
        $bounds = [[5, -1], [6, -42], [42, PHP_INT_MIN], [PHP_INT_MAX, -1]];

        foreach ($bounds as [$minimum, $maximum]) {
            $fields = new IntegerRangeCollectionFields([$minimum, 5], ['negative' => $maximum, 'other' => -1]);
            $fields->bounded = [-5, 0, 5];
            $fields->map['minimum'] = $minimum;
            $fields->map['maximum'] = $maximum;
            $fields->map['zero'] = 0;

            yield 'integer range collections: ' . $minimum . ', ' . $maximum => [$fields];
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach (['minimums', 'bounded', 'maximums', 'map'] as $field) {
            foreach (['5', 5.0, true, null, [], new stdClass()] as $value) {
                $isList = $field === 'minimums' || $field === 'bounded';
                $collection = $isList ? [5, $value] : ['valid' => -1, 'invalid' => $value];
                $path = $field . ($isList ? '[1]' : '[invalid]');

                yield 'integer range ' . $field . ' rejects ' . get_debug_type($value) => [
                    json_encode([$field => $collection], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                    IntegerRangeCollectionFields::class,
                    'Could not create '
                        . IntegerRangeCollectionFields::class
                        . ' from the JSON object: Field '
                        . $path
                        . ' must be of type int, '
                        . get_debug_type($value)
                        . ' given.',
                    3,
                ];
            }
        }

        yield from self::shapeErrors();
    }

    /** @return iterable<string, array{string, class-string, string, int}> */
    private static function shapeErrors(): iterable
    {
        foreach (['minimums' => 'list<int>', 'bounded' => 'non-empty-list<int>'] as $field => $type) {
            yield 'integer range ' . $field . ' rejects an object' => [
                '{"' . $field . '":{}}',
                IntegerRangeCollectionFields::class,
                'Could not create '
                    . IntegerRangeCollectionFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . ' must be of type '
                    . $type
                    . ', stdClass given.',
                3,
            ];
        }

        foreach (['maximums', 'map'] as $field) {
            foreach (['[]', '[-1]'] as $json) {
                yield 'integer range ' . $field . ' rejects array ' . $json => [
                    '{"' . $field . '":' . $json . '}',
                    IntegerRangeCollectionFields::class,
                    'Could not create '
                        . IntegerRangeCollectionFields::class
                        . ' from the JSON object: Field '
                        . $field
                        . ' must be of type JSON object, array given.',
                    3,
                ];
            }
        }

        yield 'integer range non-empty list rejects an empty list' => [
            '{"bounded":[]}',
            IntegerRangeCollectionFields::class,
            'Could not create '
                . IntegerRangeCollectionFields::class
                . ' from the JSON object: Field bounded must be of type non-empty-list<int>, empty list given.',
            3,
        ];

        yield 'integer range non-empty map rejects an empty object' => [
            '{"maximums":{}}',
            IntegerRangeCollectionFields::class,
            'Could not create '
                . IntegerRangeCollectionFields::class
                . ' from the JSON object: Field maximums uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            3,
        ];
    }
}
