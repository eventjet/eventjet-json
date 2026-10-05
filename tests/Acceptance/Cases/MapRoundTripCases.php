<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\MapHolder;
use Eventjet\Json\Test\Acceptance\Fixtures\PrefixedMapField;

/** @internal */
final class MapRoundTripCases
{
    /** @return iterable<string, array{MapHolder|PrefixedMapField}> */
    public static function objects(): iterable
    {
        yield 'array-valued field encoded as an object' => [new MapHolder(['answer' => 42])];
        yield 'map declaration matches the complete parameter name' => [
            new PrefixedMapField([], ['primary' => 'value']),
        ];
    }
}
