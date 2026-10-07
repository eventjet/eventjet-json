<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

/** @internal */
final class NestedTupleRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (NestedCollectionInputs::domains() as $leaf => $values) {
            foreach (NestedShapeInputs::shapes() as $shape) {
                foreach ([
                    NestedShapeInputs::declaration($shape, 'array{0: ' . $leaf . '}'),
                    'array{0: ' . NestedShapeInputs::declaration($shape, $leaf) . '}',
                ] as $index => $type) {
                    foreach (['param', 'var'] as $tag) {
                        $class = CollectionDeclarationFixture::create(
                            $index === 0 && $shape === 'ArrayObject' ? '\\ArrayObject' : 'array',
                            $type,
                            $tag,
                        );
                        $objects = [];
                        foreach ($values as $value) {
                            $objects[] = NestedCollectionInputs::fixture(
                                $index === 0 ? $shape : 'tuple',
                                $type,
                                $tag,
                                $index === 0
                                    ? NestedShapeInputs::wrap($shape, [$value])
                                    : [NestedShapeInputs::wrap($shape, $value)],
                            );
                        }
                        $batch = CollectionDeclarationFixture::create('array', 'list<\\' . $class . '>', 'param');
                        yield 'nested tuple leaf domain ' . $index . $type . $tag => [new ReflectionClass(
                            $batch,
                        )->newInstance($objects)];
                    }
                }
            }
        }
    }
}
