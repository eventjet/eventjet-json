<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/** @internal */
final class NestedTupleFieldUnionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach ([
            ['?array', 'array{list<int>}|null', [[[]], [[42]], null]],
            [
                'array|\\ArrayObject|string',
                'array{ArrayObject<string, array{int}>}|ArrayObject<string, list<array{}>>|string',
                [
                    [new ArrayObject()],
                    [new ArrayObject(['01' => [42]])],
                    new ArrayObject(),
                    new ArrayObject(['01' => [[], []]]),
                    '',
                ],
            ],
        ] as $index => [$native, $type, $values]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $type, $tag);
                $reflection = new ReflectionClass($class);
                foreach ($values as $valueIndex => $value) {
                    $object = $tag === 'param' ? $reflection->newInstance($value) : $reflection->newInstance();
                    if ($tag === 'var') {
                        $reflection->getProperty('value')->setValue($object, $value);
                    }
                    yield 'nested tuple field union ' . $index . $valueIndex . $tag => [$object];
                }
            }
        }
    }
}
