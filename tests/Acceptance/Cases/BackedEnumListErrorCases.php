<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumListField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class BackedEnumListErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'string-backed enum list rejects an integer' => [
                '{"stringStatuses":[1],"intStatuses":[]}',
                'stringStatuses[0]',
                StringBackedStatus::class,
                'expects a string backing value; int given',
            ],
            'string-backed enum list rejects an unknown value' => [
                '{"stringStatuses":["unknown"],"intStatuses":[]}',
                'stringStatuses[0]',
                StringBackedStatus::class,
                "has no case with backing value 'unknown'",
            ],
            'int-backed enum list rejects a string' => [
                '{"stringStatuses":[],"intStatuses":["1"]}',
                'intStatuses[0]',
                IntBackedStatus::class,
                'expects a int backing value; string given',
            ],
            'int-backed enum list rejects an unknown value' => [
                '{"stringStatuses":[],"intStatuses":[2]}',
                'intStatuses[0]',
                IntBackedStatus::class,
                'has no case with backing value 2',
            ],
            'public property enum list rejects an unknown value' => [
                '{"stringStatuses":[],"intStatuses":[],"publicStatuses":["unknown"]}',
                'publicStatuses[0]',
                StringBackedStatus::class,
                "has no case with backing value 'unknown'",
            ],
        ] as $name => [$json, $field, $enum, $reason]) {
            yield $name => [
                $json,
                BackedEnumListFields::class,
                'Could not create '
                    . BackedEnumListFields::class
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

        $nonBackedEnumList = new NonBackedEnumListField([NonBackedStatus::Ready]);

        yield 'empty non-backed enum list is rejected' => [
            '{"values":[]}',
            $nonBackedEnumList::class,
            'Could not create '
                . $nonBackedEnumList::class
                . ' from the JSON object: Field values uses non-backed enum '
                . NonBackedStatus::class
                . ', which cannot be decoded because it has no backing value.',
            3,
        ];
    }
}
