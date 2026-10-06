<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedSelfCollections;
use ReflectionException;
use RuntimeException;

use function str_repeat;

/** @internal */
final class NestedCollectionBoundaryCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (['list', 'ArrayObject'] as $outer) {
            foreach (['list', 'ArrayObject'] as $inner) {
                foreach ([[], [NestedCollectionInputs::wrap($inner, [])]] as $index => $items) {
                    $type = NestedCollectionInputs::declaration($outer, NestedCollectionInputs::declaration(
                        $inner,
                        'int',
                    ));
                    foreach (['param', 'var'] as $tag) {
                        yield 'nested empty shapes ' . $outer . $inner . $index . $tag => [
                            NestedCollectionInputs::fixture(
                                $outer,
                                $type,
                                $tag,
                                NestedCollectionInputs::wrap($outer, $items),
                            ),
                        ];
                    }
                }
            }
        }

        $node = new NestedSelfCollections();
        yield 'nested self empty defaults' => [$node];
        for ($depth = 1; $depth < 4; ++$depth) {
            $parent = new NestedSelfCollections([[], [$node]]);
            $parent->maps = new ArrayObject([
                '01' => new ArrayObject(['a.b' => $node, 'null' => null]),
                'empty' => new ArrayObject(),
            ]);
            $node = $parent;
            yield 'nested self and aliased maps at depth ' . $depth => [$node];
        }

        $type = str_repeat('list<', times: 63) . 'int' . str_repeat('>', times: 63);
        $value = 42;
        for ($depth = 0; $depth < 63; ++$depth) {
            $value = [$value];
        }
        yield 'nested lists at the 64-type-level boundary' => [NestedCollectionInputs::fixture(
            'list',
            $type,
            'param',
            $value,
        )];
    }
}
