<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;

/** @internal */
final class ParserSyntaxCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, \Eventjet\Json\Internal\PhpDocType|null}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function types(): iterable
    {
        yield 'literal union preserves values' => [
            "'foo'|42|true|false",
            new PhpDocType('|', [
                new PhpDocType("'foo'"),
                new PhpDocType('42'),
                new PhpDocType('true'),
                new PhpDocType('false'),
            ]),
        ];
        yield 'quoted delimiters are part of the literal' => ['"a|b,<>{}"', new PhpDocType('"a|b,<>{}"')];
        yield 'unterminated literal is rejected' => ["'foo", null];
        yield 'dangling escape is rejected' => ["'foo\\", null];
        yield 'list declaration permits surrounding whitespace' => [
            ' list < int > ',
            new PhpDocType('list', [new PhpDocType('int')]),
        ];
        yield 'intersection declaration preserves both members' => [
            'int&string',
            new PhpDocType('&', [new PhpDocType('int'), new PhpDocType('string')]),
        ];
        yield 'tuple declaration permits a trailing comma' => [
            'array{int, string,}',
            new PhpDocType(
                'array{}',
                [],
                [new PhpDocTupleEntry(new PhpDocType('int')), new PhpDocTupleEntry(new PhpDocType('string'))],
            ),
        ];
        yield from ParserDepthCases::types();
        yield 'tuple declaration rejects a mismatched closing delimiter' => ['array{int>', null];
    }
}
