<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctStringEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedBackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;

use function get_debug_type;
use function json_encode;
use function var_export;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class BackedEnumErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach (self::invalidValues() as $name => [$field, $enum, $backingType, $value, $otherField]) {
            $members = [$field => $value, ...$otherField];
            $message = get_debug_type($value) === $backingType
                ? 'which has no case with backing value ' . var_export($value, return: true) . '.'
                : 'which expects a ' . $backingType . ' backing value; ' . get_debug_type($value) . ' given.';

            yield $name => [
                json_encode($members, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                BackedEnumFields::class,
                'Could not create '
                    . BackedEnumFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . ' uses backed enum '
                    . $enum
                    . ', '
                    . $message,
                3,
            ];
            yield 'public property ' . $name => [
                json_encode([$field => $value], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                BackedEnumPublicProperties::class,
                'Could not create '
                    . BackedEnumPublicProperties::class
                    . ' from the JSON object: Field '
                    . $field
                    . ' uses backed enum '
                    . $enum
                    . ', '
                    . $message,
                3,
            ];
            yield 'nested ' . $name => [
                json_encode(['fields' => $members], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                NestedBackedEnumFields::class,
                'Could not create '
                    . BackedEnumFields::class
                    . ' from the JSON object: Field fields.'
                    . $field
                    . ' uses backed enum '
                    . $enum
                    . ', '
                    . $message,
                3,
            ];
        }

        foreach ([
            'int-backed enum union rejects an unknown integer' => [
                DistinctEnumScalarUnionField::class,
                IntBackedStatus::class,
                2,
            ],
            'string-backed enum union rejects an unknown string' => [
                DistinctStringEnumScalarUnionField::class,
                StringBackedStatus::class,
                'unknown',
            ],
        ] as $name => [$class, $enum, $value]) {
            yield $name => [
                json_encode(['value' => $value], JSON_THROW_ON_ERROR),
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses backed enum '
                    . $enum
                    . ', which has no case with backing value '
                    . var_export($value, return: true)
                    . '.',
                3,
            ];
        }

        yield 'multiple string-backed enum union rejects an unknown string' => [
            '{"value":"unknown"}',
            DisjointStringBackedEnumUnionField::class,
            'Could not create '
                . DisjointStringBackedEnumUnionField::class
                . ' from the JSON object: Field value uses backed enum union '
                . StringBackedOutcome::class
                . '|'
                . StringBackedStatus::class
                . ", which has no case with backing value 'unknown'.",
            3,
        ];

        yield 'class, enum, and scalar union rejects an unknown enum value' => [
            '{"value":"unknown"}',
            ClassEnumScalarUnionField::class,
            'Could not create '
                . ClassEnumScalarUnionField::class
                . ' from the JSON object: Field value uses backed enum '
                . StringBackedStatus::class
                . ", which has no case with backing value 'unknown'.",
            3,
        ];

        yield 'class, enum, and scalar union public property rejects an unknown enum value' => [
            '{"value":"unknown"}',
            ClassEnumScalarUnionPublicProperty::class,
            'Could not create '
                . ClassEnumScalarUnionPublicProperty::class
                . ' from the JSON object: Field value uses backed enum '
                . StringBackedStatus::class
                . ", which has no case with backing value 'unknown'.",
            3,
        ];
    }

    /** @return iterable<string, array{string, class-string, 'int'|'string', scalar|array<never, never>|null, array<string, int|string|null>}> */
    private static function invalidValues(): iterable
    {
        foreach ([
            'unknown string' => 'unknown',
            'integer' => 1,
            'float' => 1.0,
            'Boolean' => true,
            'null' => null,
            'array' => [],
        ] as $name => $value) {
            yield 'string-backed enum rejects ' . $name => [
                'stringStatus',
                StringBackedStatus::class,
                'string',
                $value,
                ['nullableStatus' => null, 'intStatus' => IntBackedStatus::Ready->value],
            ];
        }

        foreach ([
            'unknown integer' => 2,
            'numeric string' => '1',
            'float' => 1.0,
            'Boolean' => true,
            'null' => null,
            'array' => [],
        ] as $name => $value) {
            yield 'int-backed enum rejects ' . $name => [
                'intStatus',
                IntBackedStatus::class,
                'int',
                $value,
                ['stringStatus' => StringBackedStatus::Ready->value, 'nullableStatus' => null],
            ];
        }
    }
}
