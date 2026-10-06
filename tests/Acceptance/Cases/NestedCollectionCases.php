<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionException;
use RuntimeException;

/** @internal */
final class NestedCollectionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (NestedCollectionInputs::domains() as $leaf => $values) {
            foreach (NestedCollectionInputs::pairs($leaf) as [$outer, $inner]) {
                $type = NestedCollectionInputs::declaration($outer, NestedCollectionInputs::declaration($inner, $leaf));
                $value = NestedCollectionInputs::wrap($outer, [NestedCollectionInputs::wrap($inner, $values)]);
                foreach (['param', 'var'] as $tag) {
                    yield 'nested ' . $type . $tag => [NestedCollectionInputs::fixture($outer, $type, $tag, $value)];
                }
            }
        }

        yield from NullableNestedCollectionCases::objects();
    }
}
