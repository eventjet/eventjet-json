<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class BackedEnumMapErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'string-backed enum map rejects an integer' => [
                '{"stringStatuses":{"primary":1},"intStatuses":{"primary":1}}',
                'stringStatuses[primary]',
                StringBackedStatus::class,
                'expects a string backing value; int given',
            ],
            'string-backed enum map rejects an unknown value' => [
                '{"stringStatuses":{"primary":"unknown"},"intStatuses":{"primary":1}}',
                'stringStatuses[primary]',
                StringBackedStatus::class,
                "has no case with backing value 'unknown'",
            ],
            'int-backed enum map rejects a string' => [
                '{"stringStatuses":{"primary":"ready"},"intStatuses":{"primary":"1"}}',
                'intStatuses[primary]',
                IntBackedStatus::class,
                'expects a int backing value; string given',
            ],
            'int-backed enum map rejects an unknown value' => [
                '{"stringStatuses":{"primary":"ready"},"intStatuses":{"primary":2}}',
                'intStatuses[primary]',
                IntBackedStatus::class,
                'has no case with backing value 2',
            ],
            'public property enum map rejects an unknown value' => [
                '{"stringStatuses":{"primary":"ready"},"intStatuses":{"primary":1},"publicStatuses":{"primary":"unknown"}}',
                'publicStatuses[primary]',
                StringBackedStatus::class,
                "has no case with backing value 'unknown'",
            ],
        ] as $name => [$json, $field, $enum, $reason]) {
            yield $name => [
                $json,
                BackedEnumMapFields::class,
                'Could not create '
                    . BackedEnumMapFields::class
                    . ' from the JSON object: Field '
                    . $field
                    . ' uses backed enum '
                    . $enum
                    . ', which '
                    . $reason
                    . '.',
                3,
            ];
        }

        $nonBackedEnumMap = new NonBackedEnumMapField(['primary' => NonBackedStatus::Ready]);

        yield 'non-backed enum map is rejected' => [
            '{"values":{"primary":"ready"}}',
            $nonBackedEnumMap::class,
            'Could not create '
                . $nonBackedEnumMap::class
                . ' from the JSON object: Field values uses non-backed enum '
                . NonBackedStatus::class
                . ', which cannot be decoded because it has no backing value.',
            3,
        ];
    }
}
