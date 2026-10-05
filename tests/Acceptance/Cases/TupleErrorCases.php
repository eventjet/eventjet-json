<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\TupleFields;
use JsonException;
use stdClass;

use function count;
use function get_debug_type;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class TupleErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        $base = [1, 'text', 3.0, true, 'ready', 1, new Person('Ada', 'Lovelace')];
        $types = ['int', 'string', 'float', 'bool', StringBackedStatus::class, IntBackedStatus::class, Person::class];
        $declaration = 'array{' . implode(', ', $types) . '}';

        foreach (['value', 'publicValue'] as $field) {
            foreach ([[], [1], [...$base, 'extra']] as $value) {
                yield 'tuple length ' . $field . count($value) => self::error(
                    ['value' => $base, $field => $value],
                    'Field '
                    . $field
                    . ' must be of type '
                    . $declaration
                    . ' with exactly 7 items, '
                    . count($value)
                    . ' given.',
                );
            }

            foreach ([new stdClass(), (object) $base] as $index => $value) {
                yield 'tuple object shape ' . $field . $index => self::error(
                    ['value' => $base, $field => $value],
                    'Field ' . $field . ' must be of type ' . $declaration . ', stdClass given.',
                );
            }

            foreach (self::invalidItems() as $label => [$index, $wrong, $detail]) {
                $value = $base;
                $value[$index] = $wrong;
                yield 'tuple item ' . $field . $label => self::error(
                    ['value' => $base, $field => $value],
                    'Field ' . $field . '[' . $index . '] ' . $detail,
                );
            }
        }

        yield 'tuple empty rejects nonempty' => self::error([
            'value' => $base,
            'empty' => [0],
        ], 'Field empty must be of type array{} with exactly 0 items, 1 given.');
        yield 'tuple empty rejects object' => self::error([
            'value' => $base,
            'empty' => new stdClass(),
        ], 'Field empty must be of type array{}, stdClass given.');
    }

    /** @return iterable<string, array{int, array{}|int|string, string}> */
    private static function invalidItems(): iterable
    {
        foreach ([
            0 => ['1', 'int'],
            1 => [42, 'string'],
            2 => ['3.0', 'float'],
            3 => [1, 'bool'],
            6 => [[], Person::class],
        ] as $index => [$wrong, $type]) {
            yield 'type ' . $index => [
                $index,
                $wrong,
                'must be of type ' . $type . ', ' . get_debug_type($wrong) . ' given.',
            ];
        }

        yield 'string enum case' => [
            4,
            'unknown',
            'uses backed enum ' . StringBackedStatus::class . ", which has no case with backing value 'unknown'.",
        ];
        yield 'int enum case' => [
            5,
            99,
            'uses backed enum ' . IntBackedStatus::class . ', which has no case with backing value 99.',
        ];
        yield 'string enum backing' => [
            4,
            1,
            'uses backed enum ' . StringBackedStatus::class . ', which expects a string backing value; int given.',
        ];
        yield 'int enum backing' => [
            5,
            '1',
            'uses backed enum ' . IntBackedStatus::class . ', which expects a int backing value; string given.',
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array{string, class-string, string, int}
     * @throws JsonException
     */
    private static function error(array $values, string $detail): array
    {
        return [
            json_encode($values, JSON_THROW_ON_ERROR),
            TupleFields::class,
            'Could not create ' . TupleFields::class . ' from the JSON object: ' . $detail,
            3,
        ];
    }
}
