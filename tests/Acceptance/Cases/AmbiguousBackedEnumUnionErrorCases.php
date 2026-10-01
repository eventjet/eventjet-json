<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class AmbiguousBackedEnumUnionErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        $stringBackedUnionTarget = new StringBackedEnumScalarUnionField(StringBackedStatus::Ready);
        $stringBackedUnionMessage =
            'Could not create '
            . $stringBackedUnionTarget::class
            . ' from the JSON object: Field value uses backed enum '
            . StringBackedStatus::class
            . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.';

        yield 'string-backed enum with backing scalar, null value' => [
            '{"value":null}',
            $stringBackedUnionTarget::class,
            $stringBackedUnionMessage,
            3,
        ];
        yield 'string-backed enum with backing scalar, distinct scalar value' => [
            '{"value":42}',
            $stringBackedUnionTarget::class,
            $stringBackedUnionMessage,
            3,
        ];
        yield 'string-backed enum with backing scalar, field absent' => [
            '{}',
            $stringBackedUnionTarget::class,
            $stringBackedUnionMessage,
            3,
        ];

        $intBackedUnionTarget = new IntBackedEnumScalarUnionField(IntBackedStatus::Ready);
        $intBackedUnionMessage =
            'Could not create '
            . $intBackedUnionTarget::class
            . ' from the JSON object: Field value uses backed enum '
            . IntBackedStatus::class
            . ' together with its backing type int. JSON cannot distinguish an enum case from the scalar value.';

        yield 'int-backed enum with backing scalar, scalar value' => [
            '{"value":1}',
            $intBackedUnionTarget::class,
            $intBackedUnionMessage,
            3,
        ];
        yield 'int-backed enum with backing scalar, field absent' => [
            '{}',
            $intBackedUnionTarget::class,
            $intBackedUnionMessage,
            3,
        ];
    }
}
