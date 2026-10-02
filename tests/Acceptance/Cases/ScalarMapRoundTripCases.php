<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields;

/** @internal */
final class ScalarMapRoundTripCases
{
    /**
     * @api Called by RoundTripCases.
     * @return iterable<string, array{ScalarMapFields}>
     */
    public static function objects(): iterable
    {
        $fields = new ScalarMapFields(
            ['empty' => '', 'numeric' => '42', 'unicode' => 'Grüße, 世界, 😀'],
            ['zero' => 0, 'negative' => -42, 'positive' => 42],
            ['zero' => 0.0, 'whole' => 3.0, 'fraction' => -3.25],
            ['true' => true, 'false' => false],
        );
        $fields->publicStrings = ['primary' => 'value', ...$fields->floatValueTypes()];

        yield 'scalar maps preserve value types, keys, and values' => [$fields];
    }
}
