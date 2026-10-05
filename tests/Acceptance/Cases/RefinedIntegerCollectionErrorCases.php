<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\RefinedIntegerCollectionFields;
use JsonException;
use stdClass;

use function get_debug_type;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class RefinedIntegerCollectionErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach (['positive', 'nonNegative', 'negative', 'nonPositive'] as $field) {
            foreach (['1', 1.0, true, null, [], new stdClass()] as $value) {
                $isList = $field === 'positive' || $field === 'nonNegative';
                $collection = $isList ? [1, $value] : ['valid' => -1, 'invalid' => $value];
                $path = $field . ($isList ? '[1]' : '[invalid]');

                yield 'refined integer ' . $field . ' rejects ' . get_debug_type($value) => [
                    json_encode([$field => $collection], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                    RefinedIntegerCollectionFields::class,
                    'Could not create '
                        . RefinedIntegerCollectionFields::class
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
        foreach (['positive' => 'list<int>', 'nonNegative' => 'non-empty-list<int>'] as $field => $type) {
            yield 'refined integer ' . $field . ' rejects an object' => [
                '{"' . $field . '":{}}',
                RefinedIntegerCollectionFields::class,
                'Could not create '
                    . RefinedIntegerCollectionFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . ' must be of type '
                    . $type
                    . ', stdClass given.',
                3,
            ];
        }

        foreach (['negative', 'nonPositive'] as $field) {
            foreach (['[]', '[-1]'] as $json) {
                yield 'refined integer ' . $field . ' rejects array ' . $json => [
                    '{"' . $field . '":' . $json . '}',
                    RefinedIntegerCollectionFields::class,
                    'Could not create '
                        . RefinedIntegerCollectionFields::class
                        . ' from the JSON object: Field '
                        . $field
                        . ' must be of type JSON object, array given.',
                    3,
                ];
            }
        }

        yield 'refined integer non-empty list rejects an empty list' => [
            '{"nonNegative":[]}',
            RefinedIntegerCollectionFields::class,
            'Could not create '
                . RefinedIntegerCollectionFields::class
                . ' from the JSON object: Field nonNegative must be of type non-empty-list<int>, empty list given.',
            3,
        ];

        yield 'refined integer non-empty map rejects an empty object' => [
            '{"negative":{}}',
            RefinedIntegerCollectionFields::class,
            'Could not create '
                . RefinedIntegerCollectionFields::class
                . ' from the JSON object: Field negative uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
            3,
        ];
    }
}
