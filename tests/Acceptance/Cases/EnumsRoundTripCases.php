<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumUnionField;

/** @internal */
final class EnumsRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'non-backed enum alongside string' => [new NonBackedEnumUnionField('supported scalar')];
    }
}
