<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionException;
use RuntimeException;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class LiteralSyntaxRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach ([...self::values(), ...self::numericValues()] as $index => [$declaration, $native, $value]) {
            foreach (['param', 'var'] as $tag) {
                yield 'literal syntax ' . $index . ' ' . $declaration . ' in ' . $tag =>
                    [CollectionDeclarationFixture::object($native, $declaration, $tag, $value)];
                yield 'list literal syntax ' . $index . ' ' . $declaration . ' in ' . $tag =>
                    [CollectionDeclarationFixture::object('array', 'list<' . $declaration . '>', $tag, [$value])];
            }
        }
        yield from self::collectionUnions();
    }

    /** @return list<array{string, string, string|int|float|bool|StringBackedStatus|null}> */
    private static function values(): array
    {
        return [
            ['null', 'null', null],
            ["'foo'|string", 'string', 'other'],
            ['float|3.0', 'float', 4.0],
            ['\\' . ExtendedLiteralFields::class . '::NUMBER|43', 'int', 43],
            ['43|\\' . ExtendedLiteralFields::class . '::NUMBER', 'int', 43],
            ['\\' . StringBackedStatus::class . "|'other'", '\\' . StringBackedStatus::class . '|string', 'other'],
            ['\\' . ExtendedLiteralFields::class . '::CLASS', 'string', ExtendedLiteralFields::class],
            [
                '\\' . StringBackedStatus::class . '|\\' . StringBackedStatus::class . '::Ready',
                '\\' . StringBackedStatus::class,
                StringBackedStatus::Pending,
            ],
            ["'foo'", 'string', 'foo'],
            ["'foo\nbar'", 'string', "foo\nbar"],
            ["\"foo\nbar\"", 'string', "foo\nbar"],
            ['"foo"', 'string', 'foo'],
            ["'it\\'s'", 'string', "it's"],
            ['"a\\"b"', 'string', 'a"b'],
            ["'back\\\\slash'", 'string', 'back\\slash'],
            ['"\\n"', 'string', "\n"],
            ["'\\n'", 'string', '\n'],
            ['"\\x41"', 'string', 'A'],
            ['"\\101"', 'string', 'A'],
            ['"\\u{1f600}"', 'string', "\u{1f600}"],
            ['"\\u{20ac}"', 'string', "\u{20ac}"],
            ['"\\q"', 'string', '\q'],
            ['"$literal"', 'string', '$literal'],
            ['"\\$literal"', 'string', '$literal'],
            ["'foo@bar'", 'string', 'foo@bar'],
            ['"hello @var world"', 'string', 'hello @var world'],
            ['"\\r\\t\\f\\v\\e"', 'string', "\r\t\f\v\e"],
            ['"\\0"', 'string', "\0"],
        ];
    }

    /** @return list<array{string, string, string|int|float|StringBackedStatus}> */
    private static function numericValues(): array
    {
        return [
            ['0x2a', 'int', 42],
            ['0b101010', 'int', 42],
            ['0o52', 'int', 42],
            ['+4_2', 'int', 42],
            ['-0x2a', 'int', -42],
            [(string) PHP_INT_MIN, 'int', PHP_INT_MIN],
            [(string) PHP_INT_MAX, 'int', PHP_INT_MAX],
            ['3.0', 'float', 3.0],
            ['3.0|42', 'float|int', 3.0],
            ['.5', 'float', 0.5],
            ['1.', 'float', 1.0],
            ['-1.5e2', 'float', -150.0],
            ['1_2.5e+1', 'float', 125.0],
            ['PHP_INT_MAX', 'int', PHP_INT_MAX],
            ['\\PHP_INT_MIN', 'int', PHP_INT_MIN],
            ['\\' . ExtendedLiteralFields::class . '::NUMBER', 'int', 42],
            ['\\' . ExtendedLiteralFields::class . '::class', 'string', ExtendedLiteralFields::class],
            ['\\' . StringBackedStatus::class . '::Ready', '\\' . StringBackedStatus::class, StringBackedStatus::Ready],
            [
                '\\' . StringBackedStatus::class . "::Ready|'pending'",
                '\\' . StringBackedStatus::class . '|string',
                'pending',
            ],
            [
                '\\' . StringBackedStatus::class . "::Ready|'pending'",
                '\\' . StringBackedStatus::class . '|string',
                StringBackedStatus::Ready,
            ],
        ];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function collectionUnions(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            foreach ([true, false] as $boolean) {
                $name = $boolean ? 'true' : 'false';
                yield 'collection union with native ' . $name . ' ' . $tag => [CollectionDeclarationFixture::object(
                    'array|' . $name,
                    'list<42>|' . $name,
                    $tag,
                    $boolean,
                )];
            }
            yield 'collection field union with literal string ' . $tag => [CollectionDeclarationFixture::object(
                'array|string',
                "list<42>|'foo'",
                $tag,
                'foo',
            )];
            yield 'collection field union with constant ' . $tag => [CollectionDeclarationFixture::object(
                'array|int',
                'list<42>|\\' . ExtendedLiteralFields::class . '::NUMBER',
                $tag,
                42,
            )];
            yield 'collection field union with literal booleans ' . $tag => [CollectionDeclarationFixture::object(
                'array|bool',
                'list<42>|true|false',
                $tag,
                true,
            )];
        }
        yield 'parenthesized literal union' => [CollectionDeclarationFixture::object(
            'array',
            "list<('foo'|42)>",
            'param',
            ['foo', 42],
        )];
    }
}
