<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

/** @internal */
final class TupleValidationErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'indexed tuple length param1' => [
            '{"value":[1]}',
            CollectionDeclarationFixture::create('array', 'array{0: int, 1: string, 2?: bool, 3?: float}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'array{0: int, 1: string, 2?: bool, 3?: float}',
                    'param',
                )
                . ' from the JSON object: Field value must be of type array{int, string, 2?: bool, 3?: float} with between 2 and 4 items, 1 given.',
            3,
        ];
        yield 'indexed tuple shape param5' => [
            '{"value":{"0":1,"1":"text"}}',
            CollectionDeclarationFixture::create('array', 'array{0: int, 1: string, 2?: bool, 3?: float}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    'array',
                    'array{0: int, 1: string, 2?: bool, 3?: float}',
                    'param',
                )
                . ' from the JSON object: Field value must be of type array{int, string, 2?: bool, 3?: float}, stdClass given.',
            3,
        ];
        yield 'optional tuple null paramint' => [
            '{"value":[null]}',
            CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param')
                . ' from the JSON object: Field value[0] must be of type int, null given.',
            3,
        ];
        yield 'optional tuple item paramint' => [
            '{"value":["1"]}',
            CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'array{0?: int}', 'param')
                . ' from the JSON object: Field value[0] must be of type int, string given.',
            3,
        ];
    }
}
