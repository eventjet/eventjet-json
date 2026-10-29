<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use ReflectionException;
use RuntimeException;

use function str_repeat;

/**
 * Doc comments are read the way PHPStan reads them: tag priority, type grammar, and literal values.
 *
 * @internal
 */
final class PhpDocReadingCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach ([
            'a later @param overrides an earlier one' => [
                "@param list<string> \$value\n * @param list<int> \$value",
                'param',
            ],
            '@phan-param overrides @param' => [
                "@phan-param list<int> \$value\n * @param list<string> \$value",
                'param',
            ],
            '@psalm-param overrides @phan-param' => [
                "@psalm-param list<int> \$value\n * @phan-param list<string> \$value",
                'param',
            ],
            '@phpstan-param overrides @psalm-param' => [
                "@phpstan-param list<int> \$value\n * @psalm-param list<string> \$value",
                'param',
            ],
            'a named @var applies to its property' => ['@var list<int> $value', 'var'],
            '@phpstan-var overrides @var' => ["@phpstan-var list<int>\n * @var list<string>", 'var'],
            'an unnamed @var overrides a named one' => ["@var list<int>\n * @phpstan-var list<string> \$value", 'var'],
            'generic arguments may end with a comma' => ['@param list<int,> $value', 'param'],
            'the variable may directly follow the type' => ['@param list<int>$value', 'param'],
            'parentheses do not count toward the depth limit' => [
                '@param list<' . str_repeat('(', times: 64) . 'int' . str_repeat(')', times: 64) . '> $value',
                'param',
            ],
            'digit separators are removed from range bounds' => ['@param list<int<1_0, max>> $value', 'param'],
        ] as $label => [$tags, $tag]) {
            yield 'PHPDoc reading: ' . $label => [
                CollectionDeclarationFixture::object('array', "/**\n * " . $tags . "\n */", $tag, [42]),
            ];
        }
        yield 'PHPDoc reading: constant fetches may contain spaces around ::' => [
            CollectionDeclarationFixture::object(
                'int',
                '\\' . ExtendedLiteralFields::class . ' :: NUMBER',
                'param',
                42,
            ),
        ];
        foreach (['param', 'var'] as $tag) {
            yield 'PHPDoc reading: a line break inside a quoted literal leaves the native type ' . $tag => [
                CollectionDeclarationFixture::object('string', "'foo\nbar'", $tag, 'other'),
            ];
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            'a line break inside a quoted literal' => ['array', "list<'foo\nbar'>", 'param'],
            'a line break inside a quoted literal in a property' => ['array', "list<'foo\nbar'>", 'var'],
            'a space before a shape brace' => ['array', 'array {int}', 'param'],
            'a space before a shape brace in a property' => ['array', 'array {int}', 'var'],
            'a line break after a union operator' => [
                '?array',
                "/**\n * @param list<int>|\n *     null \$value\n */",
                'param',
            ],
            'the type on the line after the tag' => ['array', "/**\n * @param\n *     list<int> \$value\n */", 'param'],
            'the variable on the line after the type' => [
                'array',
                "/**\n * @param list<int>\n *     \$value\n */",
                'param',
            ],
            'a tag inside text' => ['array', '/** Text @param list<int> $value */', 'param'],
            'a second tag on the same line' => [
                'array',
                '/** @param list<string> $values @param list<int> $value */',
                'param',
            ],
            'a later @param with an unsupported type' => [
                'array',
                "/**\n * @param list<int> \$value\n * @param ?list<int> \$value\n */",
                'param',
            ],
            '64 nested lists exceed the depth limit' => [
                'array',
                str_repeat('list<', times: 64) . 'int' . str_repeat('>', times: 64),
                'param',
            ],
            '64 nested lists in a property exceed the depth limit' => [
                'array',
                str_repeat('list<', times: 64) . 'int' . str_repeat('>', times: 64),
                'var',
            ],
            'two unnamed @var tags' => ['array', "/**\n * @var list<int>\n * @var list<int>\n */", 'var'],
            'a @var named for another property' => ['array', '/** @var list<int> $other */', 'var'],
            'text after an import-type tag invalidates the whole doc comment' => [
                'array',
                "/**\n * @phpstan-import-type Foo from Bar trailing\n * @param list<int> \$value\n */",
                'param',
            ],
        ] as $label => [$native, $declaration, $tag]) {
            $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
            yield 'PHPDoc reading: ' . $label => [
                '{}',
                $class,
                self::unrecognized($class, $tag),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        foreach (['param', 'var'] as $tag) {
            $class = CollectionDeclarationFixture::create('string', '"\\u{110000}"', $tag);
            yield 'PHPDoc reading: a code point above U+10FFFF matches no JSON string ' . $tag => [
                '{"value":"x"}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value must be of type "'
                    . "\xF4\x90\x80\x80"
                    . '", string given.',
                DecodeErrorKind::InvalidValue,
            ];
        }
    }

    public static function unrecognized(string $class, string $tag): string
    {
        $tags = $tag === 'param'
            ? '@param, @phpstan-param, @psalm-param, or @phan-param'
            : '@var, @phpstan-var, @psalm-var, or @phan-var';
        return (
            'Could not create '
            . $class
            . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use '
            . $tags
            . ' with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.'
        );
    }
}
