<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function str_repeat;

/**
 * Fixed types and doc comments at the edges of the grammar: number and string literal spellings, constants, shapes,
 * line breaks inside tags, and the depth limit.
 *
 * @internal
 */
final class Examples
{
    private const array TYPES = [
        'list<int>',
        'non-empty-list<string>',
        'list<list<int|null>>',
        'ArrayObject<string, Foo>',
        '\ArrayObject<string, int>',
        'non-empty-array<string, list<int>>',
        'non-empty-map<string, int>',
        'array<string, int>',
        'array<int>',
        'int<0, max>',
        'int<min, -1>',
        'int<-5, 5>',
        'int<0x10, max>',
        'int<+5, max>',
        'int<1_0, max>',
        'int<00, max>',
        'int<1.5, max>',
        'int<-0, 0>',
        'int<9223372036854775808, max>',
        'int<min, 1e999>',
        'int|string|null',
        '(int|string)|null',
        '((int))',
        'list<int>|null',
        'null|list<int>',
        '?int',
        'int[]',
        'list<int>[]',
        'Foo&Bar',
        'int&string|null',
        'list<(int|string)>',
        'list < int >',
        'list<int,>',
        'array<string, int,>',
        'list<>',
        'list<covariant int>',
        'list<*>',
        'callable(int): void',
        '$this',
        'static',
        'self',
        'list<self>',
        'array-key',
        "'foo'|42|true|false",
        '"a|b,<>{}"',
        "'a\\'b'",
        '"a\\"b"',
        '"\\n"',
        "'\\n'",
        '"\\x41"',
        '"\\101"',
        '"\\u{1f600}"',
        '"\\$x"',
        '"\\q"',
        '"\\e"',
        '"\\0"',
        '"\\\\"',
        "'\\\\'",
        "'a\\\\\\'b'",
        '"\\u{d800}"',
        '"\\u{110000}"',
        '"\\u{ffffffff}"',
        '"\\xff"',
        '"$x"',
        "'a@b'",
        '"hello @var world"',
        "'it''s'",
        "''",
        '""',
        "'unterminated",
        "'dangling\\",
        '0x2A',
        '0b1',
        '0o7',
        '+4_2',
        '-0x2a',
        '.5',
        '1.',
        '-1.5e2',
        '1_2.5e+1',
        '1E2',
        '9223372036854775807',
        '9223372036854775808',
        '-9223372036854775808',
        '-9223372036854775809',
        '1e999',
        '-1e999',
        '0x',
        '1__0',
        '1_',
        '-',
        '+',
        '00',
        '0777',
        '1e',
        '1.5.5',
        '0_7',
        '+0',
        '-0',
        '0.0',
        '-.0e0',
        'Foo::BAR',
        'self::BAR_*',
        '\Foo\Bar::class',
        'PHP_INT_MAX',
        '\PHP_INT_MIN',
        'Foo::*',
        'static::X',
        'parent::X',
        'Foo::BAR|Foo::BAZ',
        'Foo:: BAR',
        'Foo ::BAR',
        'self::*_X',
        'self::A*B',
        'array{}',
        'array{int, string}',
        'array{int,}',
        'array{0: int, 1?: string}',
        "array{'a': int}",
        'array{a: int}',
        'array{int, ...}',
        'array{...}',
        'list{int}',
        'non-empty-array{int}',
        'array {int}',
        'array{0 : int}',
        'array{ 0?:int }',
        'array{1: int}',
        'array{+0: int}',
        'array{0x0: int}',
        'array{00: int}',
        'array{Foo::BAR: int}',
        'array{int, string}|null',
        'array{int>',
        'array{0?: int, 1: string}',
        'array{list<int>, ArrayObject<string, array{int}>}',
        'Foo-bar',
        '\Foo\Bar-baz',
        'Fü',
        'non-empty-string',
        'NULL',
        'True',
    ];

    private const array PARAMETER_DOCS = [
        "/**\n * @param array{\n *     0: int,\n *     1?: string\n * } \$value\n */",
        "/**\r\n * @param array{\r\n *     0: int,\r\n *     1?: string\r\n * } \$value\r\n */",
        "/**\n * @param int\n *     |string \$value\n */",
        "/**\n * @param int|\n *     string \$value\n */",
        "/**\n * @param list<\n *     int\n * > \$value\n */",
        "/**\n * @param list<int\n *     |string> \$value\n */",
        "/**\n * @param 'foo\n * bar' \$value\n */",
        "/**\n * @param (int\n *     |string) \$value\n */",
        '/** Text @param list<int> $value */',
        "/**\n * Text @param list<int> \$value\n */",
        '/** @param list<int> $values @param list<string> $value */',
        "/**\n * @param list<int> \$values\n * @param list<string> \$value\n */",
        '/** @param list<int>$value */',
        '/** @param list<int> $value description */',
        '/** @param list<int> $valueX */',
        '/** @param list<int> &$value */',
        '/** @param list<int> ...$value */',
        '/** @param list<int> */',
        '/** @param $value list<int> */',
        "/**\n * @param ?int \$value\n * @param list<int> \$value\n */",
        "/**\n * @param list<int> \$value\n * @param list<string> \$value\n */",
        '/** @phpstan-param list<int> $value */',
        '/** @psalm-param list<int> $value */',
        '/** @param-out list<int> $value */',
        '/** @Param list<int> $value */',
        "/**\n * Don't use this.\n * @param 'a'|'b' \$value\n */",
        "/**\n * @param 'a@b'|'c' \$value See @param.\n */",
        "/**\n * @param list<int> \$value The list.\n * @throws Exception\n * @return void\n */",
        "/**\n * @param\n *     list<int> \$value\n */",
        "/**\n * @param list<int>\n *     \$value\n */",
        "/**\n * {@inheritDoc}\n * @param list<int> \$value\n */",
        "/**\n * @param list<int> \$value\n * @psalm-type A = Foo<int, // c\n * *,\n */",
    ];

    private const array PROPERTY_DOCS = [
        '/** @var list<int> */',
        '/** @var list<int> $values */',
        '/** @var list<int> description */',
        '/** @var list<int>description */',
        '/** @var list<int>|description */',
        '/** @var list<int> |string */',
        '/** @var list<int> & */',
        '/** @var list<int> , */',
        '/** @var list<int>[] */',
        "/** @var 'foo' */",
        "/**\n * @var list<int>\n */",
        "/**\n * @var ?int\n * @var list<int>\n */",
        '/** @phpstan-var list<int> */',
        "/**\n * Text @var list<int>\n */",
    ];

    /** @return iterable<ParityInput> */
    public static function inputs(): iterable
    {
        foreach ([...self::TYPES, ...self::deep()] as $type) {
            yield ParityInput::type($type);
            yield ParityInput::doc('/** @param ' . $type . ' $value */');
            yield ParityInput::doc('/** @var ' . $type . ' */');
        }
        foreach ([...self::PARAMETER_DOCS, ...self::PROPERTY_DOCS] as $doc) {
            yield ParityInput::doc($doc);
        }
    }

    /**
     * Types nested 63 and 64 levels deep.
     *
     * @return list<string>
     */
    private static function deep(): array
    {
        $types = [];
        foreach ([63, 64] as $depth) {
            $types[] = str_repeat('list<', $depth) . 'int' . str_repeat('>', $depth);
            $types[] = str_repeat('(', $depth) . 'int' . str_repeat(')', $depth);
            $shape = 'int';
            for ($level = 0; $level < $depth; $level++) {
                $shape = 'array{0?: ' . $shape . '}';
            }
            $types[] = $shape;
        }
        return $types;
    }
}
