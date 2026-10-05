<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\RefinedStringCollectionFields;
use JsonException;
use stdClass;

use function get_debug_type;
use function in_array;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class RefinedStringCollectionCases
{
    /** @return iterable<string, array{RefinedStringCollectionFields}> */
    public static function objects(): iterable
    {
        yield 'refined string collections preserve empty lists and maps' => [new RefinedStringCollectionFields()];

        foreach (['', '0', 'Grüße, 世界, 😀', "quoted \"text\"\n", ' '] as $literal) {
            $fields = new RefinedStringCollectionFields(literalList: [$literal], literalMap: ['value' => $literal]);
            $fields->literalNonEmptyList = [$literal];
            /** @var ArrayObject<string, literal-string> $literalMap */
            $literalMap = new ArrayObject(['value' => $literal]);
            $fields->literalObjectMap = $literalMap;

            yield 'literal string collections: ' . $literal => [$fields];
        }

        foreach (['0', '-42', '01', '+1', '3.25', '1e3'] as $number) {
            foreach (['value', 'Grüße, 世界, 😀', ' '] as $label) {
                $fields = new RefinedStringCollectionFields([$label], ['number' => $number]);
                $fields->numericList = [$number];
                /** @var ArrayObject<string, non-empty-string> $labelMap */
                $labelMap = new ArrayObject(['label' => $label]);
                $fields->labelMap = $labelMap;

                yield 'refined string collections: ' . $number . ', ' . $label => [$fields];
            }
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach ([
            'labels',
            'numericList',
            'numbers',
            'labelMap',
            'literalList',
            'literalNonEmptyList',
            'literalMap',
            'literalObjectMap',
        ] as $field) {
            foreach ([42, 3.25, true, null, [], new stdClass()] as $value) {
                $isList = in_array(
                    $field,
                    ['labels', 'numericList', 'literalList', 'literalNonEmptyList'],
                    strict: true,
                );
                $collection = $isList ? ['1', $value] : ['valid' => '1', 'invalid' => $value];
                $path = $field . ($isList ? '[1]' : '[invalid]');

                yield $field . ' rejects ' . get_debug_type($value) => [
                    json_encode([$field => $collection], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                    RefinedStringCollectionFields::class,
                    'Could not create '
                        . RefinedStringCollectionFields::class
                        . ' from the JSON object: Field '
                        . $path
                        . ' must be of type string, '
                        . get_debug_type($value)
                        . ' given.',
                    3,
                ];
            }
        }

        foreach (['labels' => 'list<string>', 'numericList' => 'non-empty-list<string>'] as $field => $type) {
            yield $field . ' rejects an object' => [
                '{"' . $field . '":{}}',
                RefinedStringCollectionFields::class,
                'Could not create '
                    . RefinedStringCollectionFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . ' must be of type '
                    . $type
                    . ', stdClass given.',
                3,
            ];
        }

        yield 'refined string non-empty list rejects an empty list' => [
            '{"numericList":[]}',
            RefinedStringCollectionFields::class,
            'Could not create '
                . RefinedStringCollectionFields::class
                . ' from the JSON object: Field numericList must be of type non-empty-list<string>, empty list given.',
            3,
        ];
    }
}
