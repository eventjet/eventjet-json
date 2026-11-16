<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;

use function str_repeat;
use function var_export;

/**
 * The syntax of {@see ParserSyntaxCases}, read from doc comments on fixture classes. A value of the wrong type makes the
 * decoder print the type it read, so the failures show how the parser read each declaration.
 *
 * @internal
 */
final class ParserSyntaxDecodeCases
{
    /**
     * @return iterable<string, DecodeSuccess>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function successes(): iterable
    {
        foreach (self::declarations() as $label => [$native, $declaration, $value]) {
            yield $label => DecodeSuccess::roundTrip(CollectionDeclarationFixture::object(
                $native,
                $declaration,
                'var',
                $value,
            ));
        }
    }

    /** @return iterable<string, array{string, class-string, string, DecodeErrorKind}> */
    public static function failures(): iterable
    {
        foreach (self::printed() as $label => [$declaration, $printed]) {
            yield $label => self::mismatch('array', $declaration, '7', $printed, 'int');
        }
        foreach (self::unrecognized() as $label => $declaration) {
            $class = CollectionDeclarationFixture::create('array', $declaration, 'var');
            yield $label => [
                '{}',
                $class,
                PhpDocReadingCases::unrecognized($class, 'var'),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        yield 'the number 0x10, which PHP and PHPStan read differently, is unsupported' => self::unsupportedNumber(
            '0x10',
            'list<int<0x10, max>|int<+5, max>>',
        );
        yield 'the number +5, which PHP and PHPStan read differently, is unsupported' => self::unsupportedNumber(
            '+5',
            'list<int<+5, max>>',
        );
    }

    /**
     * A field of the native type with the declaration, decoded from a JSON value of the wrong type.
     *
     * @return array{string, class-string, string, DecodeErrorKind}
     */
    public static function mismatch(
        string $native,
        string $declaration,
        string $value,
        string $printed,
        string $given,
    ): array {
        $class = CollectionDeclarationFixture::create($native, $declaration, 'var');
        return [
            '{"value":' . $value . '}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value must be of type '
                . $printed
                . ', '
                . $given
                . ' given.',
            DecodeErrorKind::InvalidValue,
        ];
    }

    /** @return iterable<string, array{string, string, bool|float|int|string|list<mixed>}> */
    private static function declarations(): iterable
    {
        foreach (['foo', 42, true, false] as $value) {
            yield 'a literal union keeps the value ' . var_export($value, return: true) => [
                'string|int|bool',
                "'foo'|42|true|false",
                $value,
            ];
        }
        yield 'quoted delimiters are part of the literal' => ['string', '"a|b,<>{}"', 'a|b,<>{}'];
        yield '63 parentheses fit within the depth limit' => [
            'array',
            'list<' . str_repeat('(', times: 63) . 'int' . str_repeat(')', times: 63) . '>',
            [1],
        ];
        yield '63 nested lists around a literal fit within the depth limit' => [
            'array',
            str_repeat('list<', times: 63) . "'a'" . str_repeat('>', times: 63),
            self::nested('a', 62),
        ];
        yield '63 nested lists fit within the depth limit' => [
            'array',
            str_repeat('list<', times: 63) . 'int' . str_repeat('>', times: 63),
            self::nested(1, 62),
        ];
        yield '63 nested tuples fit within the depth limit' => [
            'array',
            str_repeat('array{0?: ', times: 63) . 'int' . str_repeat('}', times: 63),
            self::nested(1, 62),
        ];
        // 64 `<` make the decoder use the full parser, which applies the depth limit.
        $angles = str_repeat('<', times: 64);
        yield '63 nested lists fit within the depth limit of the full parser' => [
            'array',
            str_repeat('list<', times: 63) . "'" . $angles . "'" . str_repeat('>', times: 63),
            self::nested($angles, 62),
        ];
        yield 'a list declaration permits surrounding whitespace' => ['array', ' list < int > ', [1]];
        yield 'a tuple declaration permits a trailing comma' => ['array', 'array{int, string,}', [1, 'a']];
        yield 'digit separators are removed from numbers' => ['array', 'list<1_000|1_0.5>', [1000, 10.5]];
        yield 'strings are decoded' => ['string', '"a\\n\\"b\\$"', "a\n\"b\\\$"];
    }

    /** @return iterable<string, array{string, string}> */
    private static function printed(): iterable
    {
        yield 'a literal union is printed as written' => [
            "list<'foo'|42|true|false>",
            "list<'foo'|42|true|false>",
        ];
        yield 'quoted delimiters are printed inside the literal' => ['list<"a|b,<>{}">', 'list<"a|b,<>{}">'];
        yield 'surrounding whitespace is not printed' => [' list < int > ', 'list<int>'];
        yield 'a trailing tuple comma is not printed' => ['array{int, string,}', 'array{int, string}'];
        yield 'digit separators are not printed' => ['list<1_000|1_0.5>', 'list<1000|10.5>'];
        yield 'strings are decoded and requoted' => ['list<"a\\n\\"b\\$">', "list<\"a\n\\\"b\\\\\$\">"];
        yield 'constant fetches are printed as their value' => [
            'list<\\' . ExtendedLiteralFields::class . ' :: NUMBER>',
            'list<42>',
        ];
    }

    /** @return iterable<string, string> */
    private static function unrecognized(): iterable
    {
        yield 'an unterminated literal is rejected' => "list<'foo>";
        yield 'a dangling escape is rejected' => "list<'foo\\>";
        yield 'a missing parenthesis is rejected' => "list<('foo'|42>";
        yield '64 nested lists around a literal exceed the depth limit' =>
            str_repeat('list<', times: 64) . "'a'" . str_repeat('>', times: 64);
        yield '64 nested tuples exceed the depth limit' =>
            str_repeat('array{0?: ', times: 64) . 'int' . str_repeat('}', times: 64);
        yield 'an intersection is not a collection item' => 'list<int&string>';
        yield 'an intersection inside a union needs parentheses' => 'list<int&string|null>';
        yield 'string shape keys are decoded but not supported' => "array{'a\\'b': int}";
        yield 'constant fetch shape keys are not supported' =>
            'array{\\' . ExtendedLiteralFields::class . '::NUMBER: int}';
        yield 'a tuple declaration rejects a mismatched closing delimiter' => 'array{int>';
    }

    /** @return array{string, class-string, string, DecodeErrorKind} */
    private static function unsupportedNumber(string $number, string $declaration): array
    {
        $class = CollectionDeclarationFixture::create('array', $declaration, 'var');
        return [
            '{}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value uses unsupported number literal '
                . $number
                . ', which PHP and PHPStan can read differently. Use a plain decimal number, such as 42, -7, or 1.5.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /** @return list<mixed> */
    private static function nested(int|string $innermost, int $levels): array
    {
        return $levels === 0 ? [$innermost] : [self::nested($innermost, $levels - 1)];
    }
}
