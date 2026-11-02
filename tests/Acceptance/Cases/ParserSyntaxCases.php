<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;

use function str_repeat;

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
                self::literal('foo', "'foo'"),
                self::literal(42, '42'),
                new PhpDocType('true'),
                new PhpDocType('false'),
            ]),
        ];
        yield 'quoted delimiters are part of the literal' => ['"a|b,<>{}"', self::literal('a|b,<>{}', '"a|b,<>{}"')];
        yield 'unterminated literal is rejected' => ["'foo", null];
        yield 'dangling escape is rejected' => ["'foo\\", null];
        yield 'missing parenthesis is rejected' => ["('foo'|42", null];
        yield '63 parentheses fit within the depth limit' => [
            str_repeat('(', times: 63) . 'int' . str_repeat(')', times: 63),
            new PhpDocType('int'),
        ];
        yield 'parentheses do not count toward the depth limit' => [
            str_repeat('(', times: 64) . 'int' . str_repeat(')', times: 64),
            new PhpDocType('int'),
        ];
        $nested = self::literal('a', "'a'");
        for ($level = 0; $level < 63; ++$level) {
            $nested = new PhpDocType('list', [$nested]);
        }
        yield '63 nested lists around a literal fit within the depth limit' => [
            str_repeat('list<', times: 63) . "'a'" . str_repeat('>', times: 63),
            $nested,
        ];
        yield '64 nested lists around a literal exceed the depth limit' => [
            str_repeat('list<', times: 64) . "'a'" . str_repeat('>', times: 64),
            null,
        ];
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
        yield 'generic arguments may end with a comma' => [
            'list<int,>',
            new PhpDocType('list', [new PhpDocType('int')]),
        ];
        yield 'constant fetches may contain spaces around ::' => ['Foo :: BAR', new PhpDocType('Foo::BAR')];
        yield 'an intersection inside a union needs parentheses' => ['int&string|null', null];
        yield 'digit separators are removed from numbers' => [
            '1_000|1_0.5',
            new PhpDocType('|', [
                self::literal(1000, '1000'),
                self::literal(10.5, '10.5'),
            ]),
        ];
        yield 'strings are decoded and requoted' => [
            '"a\\n\\"b\\$"',
            self::literal("a\n\"b\\\$", "\"a\n\\\"b\\\\\$\""),
        ];
        yield 'string shape keys are decoded and requoted' => [
            "array{'a\\'b': int}",
            new PhpDocType('array{}', entries: [
                new PhpDocTupleEntry(new PhpDocType('int'), self::literal("a'b", "'a\\'b'")),
            ]),
        ];
        yield 'numbers that PHP and PHPStan read differently are unsupported' => [
            'int<0x10, max>|int<+5, max>',
            new PhpDocType('|', [
                new PhpDocType('int', [new PhpDocType('0x10', unsupportedNumber: '0x10'), new PhpDocType('max')]),
                new PhpDocType('int', [self::literal(5, '+5'), new PhpDocType('max')]),
            ]),
        ];
        yield from ParserDepthCases::types();
        yield from ParserGrammarCases::types();
        yield from self::escapes();
        yield 'tuple declaration rejects a mismatched closing delimiter' => ['array{int>', null];
    }

    /** @return iterable<string, array{string, PhpDocType}> */
    private static function escapes(): iterable
    {
        foreach ([
            '7f' => "\x7F",
            '80' => "\u{80}",
            '100' => "\u{100}",
            '7ff' => "\u{7ff}",
            '800' => "\u{800}",
            'ffff' => "\u{ffff}",
            '10000' => "\u{10000}",
            '10ffff' => "\u{10ffff}",
            'd800' => "\xED\xA0\x80",
            '110000' => "\xF4\x90\x80\x80",
            '1fffff' => "\xF7\xBF\xBF\xBF",
            '200000' => "\u{fffd}",
            '0041' => 'A',
        ] as $code => $bytes) {
            yield 'a Unicode escape is encoded as UTF-8: ' . $code => [
                '"\\u{' . $code . '}"',
                self::literal($bytes, '"' . $bytes . '"'),
            ];
        }
        foreach ([
            '\x41' => 'A',
            '\X41' => 'A',
            '\x7' => "\x07",
            '\xfF' => "\xFF",
            '\x1g' => "\x01g",
        ] as $escape => $bytes) {
            yield 'a hexadecimal escape is a byte: ' . $escape => [
                '"' . $escape . '"',
                self::literal($bytes, '"' . $bytes . '"'),
            ];
        }
    }

    private static function literal(string|int|float $value, string $name): PhpDocType
    {
        return PhpDocType::literal(new PhpDocLiteral($value, $name));
    }
}
