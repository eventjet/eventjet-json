<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

/** @internal */
final class NumericObjectKeyCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function mismatches(): iterable
    {
        yield 'numeric object keys with coercible scalar values' => [
            '{"0":42,"1":"42","2":"3.25","3":1}',
            ScalarFields::class,
        ];
    }
}
