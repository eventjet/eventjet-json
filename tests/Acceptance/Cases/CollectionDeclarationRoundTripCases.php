<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

/** @internal */
final class CollectionDeclarationRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'property declaration accepts no space before the docblock terminator' =>
            [CollectionDeclarationFixture::object('array', '/** @var list<int>*/', 'var', [42])];
        yield 'parameter description does not capture another parameter name' => [CollectionDeclarationFixture::object(
            'array',
            '/**
 * @param ?int $count Number of elements in $value
 * @param list<int> $value The actual collection
 */',
            'param',
            [42],
        )];
    }
}
