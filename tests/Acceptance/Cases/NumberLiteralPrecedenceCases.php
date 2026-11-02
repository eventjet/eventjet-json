<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use RuntimeException;

use function str_repeat;
use function strtr;

/**
 * Which unsupported number a declaration reports, and which ones do not count: those outside the tag that counts, and
 * those in a type that is unsupported anyway.
 *
 * @internal
 */
final class NumberLiteralPrecedenceCases
{
    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::PRECEDENCE as [$native, $template, $literal]) {
            foreach (['param', 'var'] as $tag) {
                $declaration = self::declaration($template, $tag);
                $case = 'first unsupported number in ' . $native . ' ' . $declaration . ' in ' . $tag;
                yield $case => NumberLiteralErrorCases::error($native, $declaration, $tag, $literal);
            }
        }
        foreach (['mixed', '\\Countable&\\Stringable'] as $native) {
            yield 'unsupported number before the native type ' . $native => NumberLiteralErrorCases::error(
                $native,
                '0x10',
                'param',
                '0x10',
            );
        }
        $repeated = "/**\n * @param 0x10 \$value\n * @param 0x20 \$value\n */";
        yield 'the last repeated parameter tag counts for unsupported numbers' => NumberLiteralErrorCases::error(
            'int',
            $repeated,
            'param',
            '0x20',
        );
    }

    /**
     * Declarations whose unsupported numbers do not count, so that the native type applies.
     *
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function ignored(): iterable
    {
        foreach (self::IGNORED as $template) {
            foreach (['param', 'var'] as $tag) {
                $declaration = self::declaration($template, $tag);
                yield 'ignored unsupported number in ' . $declaration . ' in ' . $tag => [
                    CollectionDeclarationFixture::object('int', $declaration, $tag, 1),
                ];
            }
        }
        $nested = str_repeat('list<', times: 64) . '0x10' . str_repeat('>', times: 64);
        foreach (['param', 'var'] as $tag) {
            yield 'unsupported number beyond the depth limit in ' . $tag => [
                CollectionDeclarationFixture::object('int', $nested, $tag, 1),
            ];
        }
        $unnamed = "/**\n * @var 0x10\n * @var int\n */";
        yield 'unsupported number in one of several unnamed var tags' => [
            CollectionDeclarationFixture::object('int', $unnamed, 'var', 1),
        ];
    }

    /**
     * Native types, declarations, and the number that the error reports: the first in the declaration, an integer range
     * bound or the number in it, where `{T}` is the tag and `{N}` the parameter name.
     */
    private const array PRECEDENCE = [
        ['int',          'int<list<0x10>, +5>',                               '0x10'],
        ['int',          'int<+5, 0x10>',                                     '+5'],
        ['int',          '0x20|int<+5, max>',                                 '0x20'],
        ['int',          'int<1, 2, 0x10>',                                   '0x10'],
        ['array',        'array{0x10: 0x20}',                                 '0x10'],
        ['array',        'array{a: 0x20, 0x10: int}',                         '0x20'],
        ['?array',       'list<0x10>|null',                                   '0x10'],
        ['ArrayObject',  'ArrayObject<string, 0x10>',                         '0x10'],
        ['?ArrayObject', 'ArrayObject<string, 0x10>|null',                    '0x10'],
        ['int',          "/**\n * @{T} int{N}\n * @phpstan-{T} 0x10{N}\n */", '0x10'],
    ];

    /** Declarations with unsupported numbers outside the type that counts, where `{T}` is the tag. */
    private const array IGNORED = [
        '0x10|int[]',
        '?0x10',
        '0x10|callable(): void',
        "/**\n * @{T} 0x10{N}\n * @phpstan-{T} int{N}\n */",
        "/**\n * @{T} 0x10 \$other\n * @{T} int{N}\n */",
        "/**\n * @return 0x10\n * @{T} int{N}\n */",
        "/**\n * @{T} int{N} @foo 0x10\n */",
        "/**\n * @{T} int{N}\n * @method 0x10 foo(int \$a = 0x10)\n */",
    ];

    private static function declaration(string $template, string $tag): string
    {
        return strtr($template, ['{T}' => $tag, '{N}' => $tag === 'param' ? ' $value' : '']);
    }
}
