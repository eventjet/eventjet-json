<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionException;
use RuntimeException;

/** @internal */
final class NullableNestedCollectionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (['list', 'non-empty-list', 'non-empty-array', 'ArrayObject'] as $shape) {
            foreach ([2, 3, 6] as $depth) {
                $type = 'int|null';
                $value = 42;
                for ($level = 0; $level < $depth; ++$level) {
                    $items = [$value, null];
                    if ($level > 0 && ($shape === 'list' || $shape === 'ArrayObject')) {
                        $items[] = NestedCollectionInputs::wrap($shape, []);
                    }
                    $value = NestedCollectionInputs::wrap($shape, $items);
                    $type = NestedCollectionInputs::declaration($shape, $type);
                    if ($level < ($depth - 1)) {
                        $type = ($level % 2) === 0 ? $type . '|null' : 'null|' . $type;
                    }
                }
                foreach (['param', 'var'] as $tag) {
                    yield 'nullable nested ' . $shape . $depth . $tag => [NestedCollectionInputs::fixture(
                        $shape,
                        $type,
                        $tag,
                        $value,
                    )];
                }
            }
        }

        yield from NestedCollectionBoundaryCases::objects();
    }
}
