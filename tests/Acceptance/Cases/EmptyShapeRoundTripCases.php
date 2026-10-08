<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObjectFields;

/** @internal */
final class EmptyShapeRoundTripCases
{
    /**
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'empty concrete root preserves object shape' => [new EmptyObject(), '{}'];
        yield 'empty objects, lists, and maps remain distinct' => [
            (static function (): object {
                $object = new EmptyObjectFields(new EmptyObject(), [], new ArrayObject([]));
                $object->publicObject = new EmptyObject();
                $object->publicList = [];
                $object->publicMap = new ArrayObject([]);
                return $object;
            })(),
            '{"object":{},"list":[],"map":{},"publicObject":{},"publicList":[],"publicMap":{}}',
        ];
        yield 'empty objects inside collections with key 01' => [
            (static function (): object {
                $object = new EmptyObjectFields(new EmptyObject(), [new EmptyObject()], new ArrayObject([
                    '01' => new EmptyObject(),
                ]));
                $object->publicObject = new EmptyObject();
                $object->publicList = [new EmptyObject()];
                $object->publicMap = new ArrayObject(['01' => new EmptyObject()]);
                return $object;
            })(),
            '{"object":{},"list":[{}],"map":{"01":{}},"publicObject":{},"publicList":[{}],"publicMap":{"01":{}}}',
        ];
    }
}
