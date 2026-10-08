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
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from FieldMappingErrorCases::errors();
        yield from JsonInputErrorCases::errors();
        yield from RootShapeErrorCases::errors();
        yield from UnsupportedTargetErrorCases::errors();
        yield from ConstructionErrorCases::errors();
        yield from FieldDeclarationErrorCases::errors();
        yield from ScalarValidationErrorCases::errors();
        yield from EnumsErrorCases::errors();
        yield from TupleDeclarationErrorCases::errors();
        yield from TupleValueErrorCases::errors();
        yield from CollectionsErrorCases::errors();
        yield from MapsErrorCases::errors();
        yield from AmbiguousUnionErrorCases::errors();
        yield from UnsupportedUnionErrorCases::errors();
        yield from UnionValueErrorCases::errors();
        yield from NestedValuesErrorCases::errors();
        yield from ErrorPathCases::errors();
    }
}
