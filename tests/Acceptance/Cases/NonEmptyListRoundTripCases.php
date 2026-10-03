<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonEmptyListFields;

/** @internal */
final class NonEmptyListRoundTripCases
{
    /** @return iterable<string, array{NonEmptyListFields}> */
    public static function objects(): iterable
    {
        $fields = new NonEmptyListFields([0, -42, 42]);
        $fields->labels = ['', '42', 'Grüße, 世界, 😀'];

        yield 'non-empty lists preserve item types and values' => [$fields];
    }
}
