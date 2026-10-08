<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class RootShapeErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'map root rejects an array' => [
            '[]',
            JsonType::map(EmptyObject::class),
            'Expected the JSON root to be an object, got array.',
            2,
        ];
        yield 'object root rejects an array' => [
            '[]',
            Person::class,
            'Expected the JSON root to be an object, got array.',
            2,
        ];
        yield 'array root rejects an object with numeric member names' => [
            '{"0":{}}',
            JsonType::array(Person::class),
            'Expected the JSON root to be an array, got stdClass.',
            2,
        ];
        yield 'array root rejects null' => [
            'null',
            JsonType::array(Person::class),
            'Expected the JSON root to be an array, got null.',
            2,
        ];
        yield 'object root rejects an integer' => [
            '0',
            Person::class,
            'Expected the JSON root to be an object, got int.',
            2,
        ];
    }
}
