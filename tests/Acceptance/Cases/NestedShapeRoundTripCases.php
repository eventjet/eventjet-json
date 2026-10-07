<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionException;
use RuntimeException;

/** @internal */
final class NestedShapeRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (NestedShapeInputs::shapes() as $outer) {
            foreach (NestedShapeInputs::shapes() as $middle) {
                foreach (NestedShapeInputs::shapes() as $inner) {
                    $type = NestedShapeInputs::declaration($outer, NestedShapeInputs::declaration($middle, NestedShapeInputs::declaration(
                        $inner,
                        'int',
                    )));
                    $value = NestedShapeInputs::wrap($outer, NestedShapeInputs::wrap($middle, NestedShapeInputs::wrap(
                        $inner,
                        42,
                    )));
                    foreach (['param', 'var'] as $tag) {
                        yield 'mixed nested shapes ' . $type . $tag => [NestedCollectionInputs::fixture(
                            $outer,
                            $type,
                            $tag,
                            $value,
                        )];
                    }
                }
            }
        }

        yield from NestedTupleRoundTripCases::objects();
        yield from NestedShapeBoundaryCases::objects();
    }
}
