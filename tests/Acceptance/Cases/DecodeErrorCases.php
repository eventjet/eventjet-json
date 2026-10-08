<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

/** @internal */
final class DecodeErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from TuplesErrorCases::errors();
        yield from NestedValuesErrorCases::errors();
        yield from UnionsErrorCases::errors();
        yield from FieldsErrorCases::errors();
        yield from TargetsErrorCases::errors();
        yield from EnumsErrorCases::errors();
        yield from MapsErrorCases::errors();
        yield from CollectionsErrorCases::errors();
    }
}
