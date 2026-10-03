<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\ArrayObjectMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;

use function json_encode;
use function range;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class MapKeyCases
{
    /** @return iterable<string, array{ArrayObjectMapFields|ScalarMapFields}> */
    public static function objects(): iterable
    {
        $index = 0;

        foreach (self::stringKeys() as $key) {
            $strings = [$key => 'first value', 'tail' => 'last value'];
            $arrays = new ScalarMapFields($strings, ['valid' => 42], ['valid' => 3.25], ['valid' => true]);
            $arrays->publicStrings = $strings;

            yield 'non-empty maps preserve string key ' . $index => [$arrays];

            $objects = new ArrayObjectMapFields(new ArrayObject($strings), new ArrayObject());
            /** @var ArrayObject<string, StringBackedStatus> $statuses */
            $statuses = new ArrayObject([$key => StringBackedStatus::Ready, 'tail' => StringBackedStatus::Pending]);
            $objects->statuses = $statuses;

            yield 'ArrayObject maps preserve string key ' . $index => [$objects];
            ++$index;
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        $targets = [
            [
                ScalarMapFields::class,
                ['strings', 'publicStrings'],
                [
                    'strings' => ['valid' => 'value'],
                    'integers' => ['valid' => 42],
                    'floats' => ['valid' => 3.25],
                    'booleans' => ['valid' => true],
                ],
            ],
            [
                ArrayObjectMapFields::class,
                ['strings', 'statuses'],
                ['strings' => (object) [], 'people' => (object) []],
            ],
        ];

        foreach (self::integerKeyMaps() as $name => [$map, $key]) {
            foreach ($targets as [$class, $fields, $valid]) {
                foreach ($fields as $field) {
                    $document = $valid;
                    $document[$field] = (object) $map;

                    yield $class . ' ' . $field . ' rejects ' . $name => [
                        json_encode($document, JSON_THROW_ON_ERROR),
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field '
                            . $field
                            . ' has numeric-looking member name '
                            . $key
                            . ', which PHP converts to an integer array key. Supported maps require member names that remain strings.',
                        3,
                    ];
                }
            }
        }
    }

    /** @return iterable<int, string> */
    private static function stringKeys(): iterable
    {
        yield from ['', 'plain', 'Grüße, 世界, 😀', 'quote"', 'slash\\', "line\nbreak", '-0', '-01'];
        yield (string) PHP_INT_MAX . '0';
        yield (string) PHP_INT_MIN . '0';

        foreach (range(0, end: 20) as $integer) {
            yield '0' . $integer;
            yield '+' . $integer;
            yield $integer . '.0';
            yield $integer . 'e0';
            yield ' ' . $integer;
            yield $integer . ' ';
        }
    }

    /** @return iterable<string, array{array<array-key, string>, int}> */
    private static function integerKeyMaps(): iterable
    {
        foreach ([PHP_INT_MIN, ...range(-10, end: 10), PHP_INT_MAX] as $key) {
            yield 'integer member ' . $key => [[$key => 'ready'], $key];
            yield 'integer member after string member ' . $key => [['valid' => 'ready', $key => 'pending'], $key];
        }

        yield 'non-sequential integer members' => [[7 => 'ready', 2 => 'pending'], 7];
        yield 'out-of-order integer members' => [[1 => 'ready', 0 => 'pending'], 1];
    }
}
