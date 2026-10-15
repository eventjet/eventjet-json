<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
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
        yield 'root map rejects a null object' => [
            '{"author":null}',
            JsonType::map(Person::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field [author] must be of type Eventjet\Json\Test\Acceptance\Fixtures\Person, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'root enum list rejects null' => [
            '[null]',
            JsonType::array(IntBackedStatus::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus from the JSON object: Field [0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus, which expects a int backing value; null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'nested root list rejects null' => [
            '[null]',
            JsonType::array(JsonType::array(Person::class)),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field [0] must be of type list<Eventjet\Json\Test\Acceptance\Fixtures\Person>, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'nested root map rejects null' => [
            '[null]',
            JsonType::array(JsonType::map(Person::class)),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Field [0] must be of type JSON object, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'map root rejects an array' => [
            '[]',
            JsonType::map(EmptyObject::class),
            'Expected the JSON root to be an object, got array.',
            DecodeErrorKind::UnexpectedRootValue->value,
        ];
        yield 'object root rejects an array' => [
            '[]',
            Person::class,
            'Expected the JSON root to be an object, got array.',
            DecodeErrorKind::UnexpectedRootValue->value,
        ];
        yield 'array root rejects an object with numeric member names' => [
            '{"0":{}}',
            JsonType::array(Person::class),
            'Expected the JSON root to be an array, got stdClass.',
            DecodeErrorKind::UnexpectedRootValue->value,
        ];
        yield 'array root rejects null' => [
            'null',
            JsonType::array(Person::class),
            'Expected the JSON root to be an array, got null.',
            DecodeErrorKind::UnexpectedRootValue->value,
        ];
        yield 'object root rejects an integer' => [
            '0',
            Person::class,
            'Expected the JSON root to be an object, got int.',
            DecodeErrorKind::UnexpectedRootValue->value,
        ];
    }
}
