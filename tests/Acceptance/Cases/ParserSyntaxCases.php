<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;

use function bin2hex;

/** @internal */
final class ParserSyntaxCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, PhpDocType|null}>
     */
    public static function types(): iterable
    {
        foreach ([
            'int',
            'string',
            'bool',
            'float',
            'non-empty-string',
            'positive-int',
            '\\Foo\\Bar',
            'ÜberPerson',
            '-999999999999999999999999999',
        ] as $name) {
            foreach (['', ' ', "\t", "\r\n", "\v\f"] as $whitespace) {
                $leaf = new PhpDocType($name);
                yield 'leaf ' . $name . ' ' . bin2hex($whitespace) => [$whitespace . $name . $whitespace, $leaf];
                $source =
                    $whitespace . 'list' . $whitespace . '<' . $whitespace . $name . $whitespace . '>' . $whitespace;
                yield 'list ' . $name . ' ' . bin2hex($whitespace) => [$source, new PhpDocType('list', [$leaf])];
            }
        }

        foreach (['list', 'non-empty-list', 'ArrayObject', 'non-empty-array', 'array{}'] as $outer) {
            foreach (['list', 'ArrayObject', 'array{}'] as $inner) {
                $leaf = new PhpDocType('int');
                $nested = self::containerType($inner, $leaf);
                yield $outer . ' containing ' . $inner => [
                    self::container($outer, self::container($inner, 'int')),
                    self::containerType($outer, $nested),
                ];
            }
        }

        yield from ParserCompoundCases::types();

        yield 'empty tuple' => ['array{}', new PhpDocType('array{}')];
        yield 'trailing comma' => [
            'array{int,}',
            new PhpDocType('array{}', entries: [new PhpDocTupleEntry(new PhpDocType('int'))]),
        ];
        yield 'multi-item trailing comma' => [
            'array{int, string,}',
            new PhpDocType('array{}', entries: [
                new PhpDocTupleEntry(new PhpDocType('int')),
                new PhpDocTupleEntry(new PhpDocType('string')),
            ]),
        ];
        yield 'two generic arguments' => [
            'int<min, max>',
            new PhpDocType('int', [new PhpDocType('min'), new PhpDocType('max')]),
        ];
        yield 'mixed tuple keys stay textual' => [
            'array{0: int, 999999999999999999999999?: list<string>, name: array{bool}}',
            new PhpDocType('array{}', entries: [
                new PhpDocTupleEntry(new PhpDocType('int'), key: '0'),
                new PhpDocTupleEntry(
                    new PhpDocType('list', [new PhpDocType('string')]),
                    key: '999999999999999999999999',
                    optional: true,
                ),
                new PhpDocTupleEntry(new PhpDocType('array{}', entries: [new PhpDocTupleEntry(
                    new PhpDocType('bool'),
                )]), key: 'name'),
            ]),
        ];
        yield 'optional tuple trailing comma' => [
            'array{0?: int,}',
            new PhpDocType('array{}', entries: [new PhpDocTupleEntry(new PhpDocType('int'), key: '0', optional: true)]),
        ];

        yield from self::boundaries();
    }

    /** @return iterable<string, array{string, PhpDocType|null}> */
    private static function boundaries(): iterable
    {
        yield from ParserDepthCases::types();

        foreach ([
            '',
            '?',
            'int|',
            'int&',
            '|int',
            'int||string',
            'int&&string',
            'list<int|>',
            'list<>',
            'array{:int}',
            'array{0?:}',
            'array{0??:int}',
            'list<int,>',
            'list<int,,string>',
            'array{int,,}',
            'list<int}',
            'array{int>',
            'list<int>junk',
            '(int)',
            'int[]',
            'list<\\>',
            'list<\\\\Foo>',
            'list<Foo\\>',
        ] as $source) {
            yield 'invalid ' . $source => [$source, null];
        }
    }

    private static function containerType(string $name, PhpDocType $item): PhpDocType
    {
        return (
            $name === 'array{}'
                ? new PhpDocType($name, entries: [new PhpDocTupleEntry($item)])
                : new PhpDocType($name, [$item])
        );
    }

    private static function container(string $name, string $item): string
    {
        return $name === 'array{}' ? 'array{' . $item . '}' : $name . '<' . $item . '>';
    }
}
