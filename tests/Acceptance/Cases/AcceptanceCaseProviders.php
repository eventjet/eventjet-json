<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\JsonType;
use Throwable;

/**
 * Factories keep case generation under coverage and out of unrelated mutant runs.
 *
 * @internal
 * @phpstan-type RoundTripCase array{0: list<mixed>|object, 1?: string|null, 2?: JsonType<list<mixed>|object>|(Closure(): JsonType<list<mixed>|object>)|null}
 * @phpstan-type ErrorCase array{0: string, 1: class-string|JsonType<list<mixed>|object>, 2: string, 3: int, 4?: Throwable}
 */
final class AcceptanceCaseProviders
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{(Closure(): iterable<string, RoundTripCase>)}>
     */
    public static function roundTrips(): iterable
    {
        yield 'RoundTripCases::objects' => [RoundTripCases::objects(...)];
        yield 'RoundTripCases::scalarFields' => [RoundTripCases::scalarFields(...)];
        yield 'RoundTripCases::numericBoundaries' => [RoundTripCases::numericBoundaries(...)];
        yield 'RoundTripCases::stringFields' => [RoundTripCases::stringFields(...)];
        yield 'RoundTripCases::memberOrders' => [RoundTripCases::memberOrders(...)];
        yield 'RoundTripCases::objectRootWhitespace' => [RoundTripCases::objectRootWhitespace(...)];
        yield 'PublicPropertyUnionRoundTripCases::objects' => [PublicPropertyUnionRoundTripCases::objects(...)];
        yield 'RootArrayRoundTripCases::objects' => [RootArrayRoundTripCases::objects(...)];
        yield 'RootArrayEnumRoundTripCases::enums' => [RootArrayEnumRoundTripCases::enums(...)];
        yield 'RootArrayRoundTripCases::shapes' => [RootArrayRoundTripCases::shapes(...)];
        yield 'RootArrayTypeInferenceCases::values' => [RootArrayTypeInferenceCases::values(...)];
        yield 'RootMapRoundTripCases::values' => [RootMapRoundTripCases::values(...)];
        yield 'RootMapTypeInferenceCases::values' => [RootMapTypeInferenceCases::values(...)];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{(Closure(): iterable<string, ErrorCase>)}>
     */
    public static function errors(): iterable
    {
        yield 'RootArrayErrorCases::errors' => [RootArrayErrorCases::errors(...)];
        yield 'RootMapErrorCases::errors' => [RootMapErrorCases::errors(...)];
        yield 'RootArrayConstructionCases::exceptions' => [RootArrayConstructionCases::exceptions(...)];
        yield 'RootValueErrorCases::unexpectedRootValues' => [RootValueErrorCases::unexpectedRootValues(...)];
        yield 'RootValueErrorCases::arrayRootValues' => [RootValueErrorCases::arrayRootValues(...)];
        yield 'DecodeErrorCases::constructionFailures' => [DecodeErrorCases::constructionFailures(...)];
        yield 'DecodeErrorCases::malformedDocuments' => [DecodeErrorCases::malformedDocuments(...)];
        yield 'DecodeErrorCases::documentsWithTrailingContent' => [DecodeErrorCases::documentsWithTrailingContent(...)];
        yield 'DecodeErrorCases::invalidUtf8Documents' => [DecodeErrorCases::invalidUtf8Documents(...)];
        yield 'DecodeErrorCases::deeplyNestedDocuments' => [DecodeErrorCases::deeplyNestedDocuments(...)];
        yield 'NonBackedEnumErrorCases::errors' => [NonBackedEnumErrorCases::errors(...)];
        yield 'ScalarTypeMismatchCases::mismatches' => [ScalarTypeMismatchCases::mismatches(...)];
        yield 'ScalarTypeMismatchCases::outOfRangeIntegers' => [ScalarTypeMismatchCases::outOfRangeIntegers(...)];
    }
}
