<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocType;

/**
 * Constant expressions and shape keys for {@see ParserGrammarCases}, with the types that the parser returns for them.
 *
 * @internal
 */
final class ParserGrammarConstants
{
    /**
     * Pairs of a constant expression and the type that the parser returns for it.
     *
     * @return non-empty-list<array{string, PhpDocType}>
     */
    public static function constants(): array
    {
        return [
            ['42', self::literal(42, '42')],
            ['-7', self::literal(-7, '-7')],
            ['+3', self::literal(3, '+3')],
            ['1_000', self::literal(1000, '1000')],
            ['0', self::literal(0, '0')],
            ['0x1F', self::unsupported('0x1F')],
            ['0X1f', self::unsupported('0X1f')],
            ['0b101', self::unsupported('0b101')],
            ['0B1_0', self::unsupported('0B10')],
            ['0o17', self::unsupported('0o17')],
            ['0O7', self::unsupported('0O7')],
            ['017', self::unsupported('017')],
            ['1.5', self::literal(1.5, '1.5')],
            ['.5', self::literal(0.5, '.5')],
            ['1.', self::literal(1.0, '1.')],
            ['-1e3', self::literal(-1000.0, '-1e3')],
            ['1E+3', self::literal(1000.0, '1E+3')],
            ['1_0.5_0e-1_0', self::literal(10.50e-10, '10.50e-10')],
            ["'foo'", self::literal('foo', "'foo'")],
            ["''", self::literal('', "''")],
            ['""', self::literal('', '""')],
            ["'it\\'s'", self::literal("it's", "'it\\'s'")],
            ["'a\\\\b'", self::literal('a\\b', "'a\\\\b'")],
            ["'a\\nb'", self::literal('a\\nb', "'a\\\\nb'")],
            ["'\"'", self::literal('"', "'\"'")],
            ['"a\\"b"', self::literal('a"b', '"a\\"b"')],
            ['"\\n\\t\\r\\v\\f\\e"', self::literal("\n\t\r\v\f\e", "\"\n\t\r\v\f\e\"")],
            ['"\\x41\\X4a\\101\\0"', self::literal("AJA\0", "\"AJA\0\"")],
            [
                '"\\u{41}\\u{e9}\\u{20ac}\\u{1f600}"',
                self::literal("A\u{e9}\u{20ac}\u{1f600}", "\"A\u{e9}\u{20ac}\u{1f600}\""),
            ],
            ['"\\$a\\q\\\\"', self::literal('\\$a\\q\\', '"\\\\$a\\\\q\\\\"')],
            ["'\\u{41}'", self::literal('\\u{41}', "'\\\\u{41}'")],
            ['"<>{}|,()"', self::literal('<>{}|,()', '"<>{}|,()"')],
            ['Foo::BAR', new PhpDocType('Foo::BAR')],
            ['\\Foo\\Bar::BAZ_9', new PhpDocType('\\Foo\\Bar::BAZ_9')],
            ['Foo::BAR_*', new PhpDocType('Foo::BAR_*')],
            ['Foo::*', new PhpDocType('Foo::*')],
            ['Foo::*_BAR', new PhpDocType('Foo::*_BAR')],
            ['Foo::A*B', new PhpDocType('Foo::A*B')],
            ['self::class', new PhpDocType('self::class')],
            ['Foo :: BAR', new PhpDocType('Foo::BAR')],
            ['Foo::is', new PhpDocType('Foo::is')],
        ];
    }

    /**
     * Pairs of a shape key and the type that the parser returns for it.
     *
     * @return non-empty-list<array{string, PhpDocType}>
     */
    public static function keys(): array
    {
        return [
            ['0', self::literal(0, '0')],
            ['1_2', self::literal(12, '12')],
            ['-1', self::literal(-1, '-1')],
            ['0x1', self::unsupported('0x1')],
            ['foo', new PhpDocType('foo')],
            ['Foo\\Bar', new PhpDocType('Foo\\Bar')],
            ["'a b'", self::literal('a b', "'a b'")],
            ["'it\\'s'", self::literal("it's", "'it\\'s'")],
            ['"\\x41"', self::literal('A', '"A"')],
            ['Foo::BAR', new PhpDocType('Foo::BAR')],
            ['int', new PhpDocType('int')],
            ['list', new PhpDocType('list')],
        ];
    }

    private static function literal(string|int|float $value, string $name): PhpDocType
    {
        return PhpDocType::literal(new PhpDocLiteral($value, $name));
    }

    private static function unsupported(string $number): PhpDocType
    {
        return new PhpDocType($number, unsupportedNumber: $number);
    }
}
