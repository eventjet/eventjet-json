<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\FinalClassMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class FinalClassMapRoundTripCases
{
    /**
     * @api Called by RoundTripCases.
     * @return iterable<string, array{FinalClassMapFields}>
     */
    public static function objects(): iterable
    {
        $fields = new FinalClassMapFields([
            'author' => new Person('Ada', 'Lovelace'),
            'reviewer' => new Person('Grace', 'Hopper', age: 85),
        ]);
        $fields->publicPeople = ['maintainer' => new Person('Margaret', 'Hamilton')];

        yield 'final class maps preserve item types, keys, and values' => [$fields];
    }
}
