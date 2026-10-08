<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;

/** @internal */
final class CollectionsRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'scalar lists preserve item types and values' => [new ScalarListFields(
            ['', '42', 'Grüße, 世界, 😀'],
            [0, -42, 42],
            [0.0, 3.0, -3.25],
            [true, false],
            ['float', 'float', 'float'],
        )];
    }
}
