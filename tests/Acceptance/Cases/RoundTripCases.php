<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

/** @internal */
final class RoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)|null}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from JsonFormattingRoundTripCases::objects();
        yield from RootCollectionRoundTripCases::objects();
        yield from EmptyShapeRoundTripCases::objects();
        yield from ObjectHydrationRoundTripCases::objects();
        yield from StringValueRoundTripCases::objects();
        yield from NumericBoundaryRoundTripCases::objects();
        yield from ScalarFieldRoundTripCases::objects();
        yield from NestedValuesRoundTripCases::objects();
        yield from NameResolutionRoundTripCases::objects();
        yield from CollectionsRoundTripCases::objects();
        yield from TargetsRoundTripCases::objects();
        yield from UnionsRoundTripCases::objects();
        yield from EnumsRoundTripCases::objects();
        yield from MapsRoundTripCases::objects();
    }
}
