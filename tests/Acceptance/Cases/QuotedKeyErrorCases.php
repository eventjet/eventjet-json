<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

/** @internal */
final class QuotedKeyErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'quoted map path "a\"b"param' => [
            '{"value":{"a\"b":"wrong"}}',
            CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', 'param')
                . ' from the JSON object: Field value["a\"b"] must be of type int, string given.',
            3,
        ];
    }
}
