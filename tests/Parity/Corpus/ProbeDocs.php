<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use Eventjet\Json\Test\Acceptance\Cases\ParserSyntaxCases;

use function array_map;
use function implode;
use function preg_match;
use function str_contains;
use function str_replace;
use function strlen;
use function substr;

/**
 * Enumerates doc comments that probe how phpdoc-parser ends a tag. Most start with a `@param` tag and a type alias that
 * fails after a comment. The comment then makes the doc comment unreadable unless a later complete type takes it, so the
 * `@param` tag counts only if the tag after the alias parses.
 *
 * @internal
 */
final class ProbeDocs
{
    /** A `@param` tag and a type alias that fails after a comment. */
    public const string HEAD = "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * ";

    private const array GAP_TYPES = [
        'array{...<int>, }',
        'array{...<int, string>,}',
        'list{...<int>}',
        'array{a: int, ...}',
        'array{a: int, ...<int>}',
        'object{a: int, \'b\'?: string,}',
        'object{}',
        'callable<T>(T $a, int ...$b): void',
        'Closure<T of int, U>(): T',
        'Foo<int, string,>',
        'Foo<covariant int, *>',
        '(T is int ? A : B)',
        '($x is not int ? A : B)',
        'T is int ? A : B',
        'int[]',
        'array{a: int,}',
        'Closure(int $a = 1): (int|string)',
        'callable(int &...$a,): int',
        'int<0, max>',
        'Foo::BAR|Foo::*',
        '\'a\'|"b"',
        '?int',
        '(int)',
        'int&string',
        'array{int, string}',
        'array{\'a\'?: int}',
        'array{1: int}',
        'array{A::B: int}',
        'list<int>',
        'array<int, string>',
        'int|string|null',
        '(int|string)[]',
        'array{a: array{b: int}}',
        'list{int, ...}',
        'callable(): void',
        'Foo::BAR',
        'int<min, 5>',
        'key-of<Foo>',
        '$this',
        'static',
        'Foo[]|null',
        '(callable(): int)|null',
        'array{a?: int, b: string}',
        'object{a: int}|int',
    ];

    private const array GAPS = ["\n * ", "\n * // x\n * ", " // x\n * ", "\n *\n * "];

    private const array METHOD_STARTS = ['', 'static ', 'static static '];

    private const array METHOD_NAMES = [
        'foo',
        'Foo',
        'Foo::BAR',
        'Foo[]',
        '$this',
        'foo<T>',
        'int',
        'static',
        'Foo|Bar',
        '?Foo',
        'Foo&Bar',
        '(Foo)',
        '\Foo',
        'Foo\Bar',
        '1',
        "'a'",
        'Foo::*',
    ];

    private const array METHOD_GAPS = ['', ' ', "\n * ", " // x\n * ", "\n *\n * ", "\t"];

    private const array METHOD_RESTS = [
        '()',
        ' foo()',
        ' bar()',
        '<T>()',
        ' foo<T>()',
        '::foo()',
        ' ::foo()',
        '(int $a)',
        ' (int $a)',
        '',
        ' foo',
        '()x',
    ];

    /**
     * Cuts each parser case with a comment at every position, and uses the cut in a type alias after a `@param` tag.
     * Comments survive only failed alias types, so these show which parsers keep comments.
     *
     * @return iterable<ParityInput>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function aliases(int $stride): iterable
    {
        foreach (ParserSyntaxCases::types() as [$source]) {
            if (!str_contains($source, '//')) {
                continue;
            }
            for ($length = 1; $length <= strlen($source); $length += $stride) {
                $prefix = substr($source, offset: 0, length: $length);
                foreach ([$prefix, $prefix . '|', $prefix . ' !'] as $type) {
                    yield ParityInput::doc(
                        "/**\n * @param list<int> \$value\n * @psalm-type A = " . self::indent($type) . "\n */",
                    );
                }
            }
        }
    }

    /**
     * Inserts a line end or a comment at each gap between the tokens of a type, in a plain tag and behind the type alias.
     *
     * @return iterable<ParityInput>
     */
    public static function gaps(): iterable
    {
        foreach (self::GAP_TYPES as $type) {
            foreach (self::gapVariants($type) as $variant) {
                yield ParityInput::doc("/**\n * @param " . $variant . " \$value\n */");
                yield ParityInput::doc(
                    "/**\n * @param list<int> \$value\n * @psalm-param " . $variant . " \$value\n */",
                );
                yield ParityInput::doc(self::HEAD . '@var ' . $variant . "\n */");
                yield ParityInput::doc(self::HEAD . '@method ' . $variant . " foo()\n */");
            }
        }
    }

    /**
     * `@method` tags whose return type or name is split by line ends and comments.
     *
     * @return iterable<ParityInput>
     */
    public static function methods(): iterable
    {
        $parts = Product::of(self::METHOD_STARTS, self::METHOD_NAMES, self::METHOD_GAPS, self::METHOD_RESTS);
        foreach ($parts as $method) {
            yield ParityInput::doc(self::HEAD . '@method ' . implode('', $method) . "\n */");
        }
    }

    /**
     * Returns the type with each gap between its tokens replaced by a line end or a comment.
     *
     * @return list<string>
     */
    private static function gapVariants(string $type): array
    {
        $variants = [];
        for ($offset = 0; $offset <= strlen($type); $offset++) {
            $space = (int) preg_match('~\G\s~', $type, offset: $offset);
            $boundary = preg_match(
                '~\G(?:(?<=\w)(?=\W)|(?<=\W)(?=\w)|(?<=[^\w\s])(?=[^\w\s])|\z)~',
                $type,
                offset: $offset,
            );
            if ($space !== 1 && $boundary !== 1) {
                continue;
            }
            $before = substr($type, offset: 0, length: $offset);
            $after = substr($type, offset: $offset + $space);
            $variants = [
                ...$variants,
                ...array_map(static fn(string $gap): string => $before . $gap . $after, self::GAPS),
            ];
        }
        return $variants;
    }

    /** Continues the lines of a type in a doc comment. */
    public static function indent(string $type): string
    {
        return str_replace(search: "\n", replace: "\n * ", subject: $type);
    }
}
