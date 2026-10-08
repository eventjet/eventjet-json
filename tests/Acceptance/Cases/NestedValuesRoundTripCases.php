<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedSelfCollections;
use Eventjet\Json\Test\Acceptance\Fixtures\RecursiveNode;

/** @internal */
final class NestedValuesRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'nested self empty defaults' => [(static function (): object {
            $object = new NestedSelfCollections();
            $object->maps = new ArrayObject([]);
            return $object;
        })()];
        yield 'recursively nested self type' => [new RecursiveNode('root', new RecursiveNode('leaf', null))];
    }
}
