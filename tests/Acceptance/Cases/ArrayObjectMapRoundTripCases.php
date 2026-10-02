<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\ArrayObjectMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class ArrayObjectMapRoundTripCases
{
    /** @return iterable<string, array{ArrayObjectMapFields}> */
    public static function objects(): iterable
    {
        $empty = new ArrayObjectMapFields(new ArrayObject(), new ArrayObject());
        /** @var ArrayObject<string, StringBackedStatus> $emptyStatuses */
        $emptyStatuses = new ArrayObject();
        $empty->statuses = $emptyStatuses;

        yield 'empty ArrayObject maps preserve JSON object shape' => [$empty];

        $populated = new ArrayObjectMapFields(
            new ArrayObject(['primary' => 'value']),
            new ArrayObject(['author' => new Person('Ada', 'Lovelace')]),
        );
        /** @var ArrayObject<string, StringBackedStatus> $statuses */
        $statuses = new ArrayObject(['primary' => StringBackedStatus::Ready]);
        $populated->statuses = $statuses;

        yield 'ArrayObject maps preserve scalar, class, and enum values' => [$populated];
    }
}
