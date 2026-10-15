<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function str_ends_with;

/**
 * Builds doc comments in and around the part of the grammar that the fast path reads: DTO doc comments with single-line
 * `@param` and `@var` tags and text, which {@see self::mutate()} pushes across the edges of that part.
 *
 * @internal
 */
final readonly class SimpleDocs
{
    private const array NAMES = [
        'int',
        'string',
        'bool',
        'float',
        'null',
        'true',
        'false',
        'mixed',
        'list',
        'array',
        'non-empty-list',
        'non-empty-array',
        'ArrayObject',
        '\ArrayObject',
        'Foo',
        '\Foo\Bar',
        'Foo\Bar',
        'self',
        'static',
        'positive-int',
        'non-empty-string',
        'covariant',
        'contravariant',
        'is',
        'of',
        'as',
        'callable',
        'Closure',
        'object',
        'max',
        'min',
        'Foo-',
        'key-of',
        'value-of',
        'TRUE',
        'Null',
        'int-mask',
        'array-key',
        'class-string',
        'iterable',
        'b',
        'p',
        'Array',
    ];

    private const array GENERICS = ['list', 'ArrayObject', 'array', 'non-empty-list', 'Foo', 'int', 'non-empty-array'];

    private const array STRINGS = ["'a'", "'b c'", '"a"', '"b"', "''", "'a@b'", "'a*b'", "'$'", '"x y"', "'it''s'"];

    private const array INTEGERS = ['0', '1', '-1', '42', '007', '-0', '9223372036854775807', '99999999999999999999'];

    private const array CLASSES = ['self', 'static', 'Foo', '\Foo\Bar', 'TRUE', 'null', 'Array', 'parent'];

    private const array CONSTANTS = ['A', 'BAR', 'STATUS_OK', '_X', 'A1', 'class', 'A_*', '*'];

    private const array TAGS = [
        '@param',
        '@param',
        '@param',
        '@var',
        '@var',
        '@var',
        '@phpstan-param',
        '@psalm-param',
        '@phan-param',
        '@phpstan-var',
        '@psalm-var',
        '@phan-var',
    ];

    private const array TEXT = [
        'Summary line.',
        'The values.',
        'Uses list<int> internally.',
        'See https://example.com/a',
        'a | b',
        '| leading pipe',
        '& leading amp',
        '{@inheritDoc}',
        "it's",
        'He said "hi',
        '"quoted"',
        '@internal',
        '@deprecated use x',
        '@throws Foo',
        '@return int',
        '* star',
        '[x]',
        '// comment',
        '<b>bold</b>',
        'x </int> y',
        '(a)',
        '$value is used',
        'é',
        '@Foo(1)',
        '@param',
        'text @param int $value',
        '- item',
        '',
        '',
        '',
    ];

    private const array VARIABLES = [
        '$value',
        '$value',
        '$values',
        '$other',
        '$this',
        '$thisValue',
        '$Value',
        '$x1',
        '$_',
    ];

    private const array MUTATIONS = [
        ' ',
        "\t",
        '',
        '|',
        '&',
        '<',
        '>',
        ',',
        '?',
        '[',
        ']',
        '[]',
        '(',
        ')',
        '{',
        '}',
        ':',
        '::',
        '*',
        '/',
        '"',
        "'",
        '\\',
        '$',
        '@',
        '.',
        '...',
        '-',
        '=',
        '!',
        "\n",
        "\r",
        "\r\n",
        "\n * ",
        "\n * |null",
        'covariant ',
        ' is ',
        '0',
        '-1',
        '1.5',
        '1_0',
        '0x1',
        '//',
        '*/',
        "\x0b",
        "\xC3\xA9",
        "\xA0",
        '#',
        '<b>',
        '</b>',
    ];

    public function __construct(
        private Dice $dice,
    ) {}

    public function doc(): string
    {
        $oneLine = $this->dice->chance(30);
        if ($oneLine) {
            return '/** ' . $this->tagLine() . ' */';
        }
        $eol = $this->dice->either(90, "\n", "\r\n");
        $indent = $this->dice->pick(['', '    ', '        ', "\t"]);
        $doc = '/**';
        for ($line = $this->dice->int(1, 5); $line > 0; $line--) {
            $tagged = $this->dice->chance(70);
            $content = $tagged ? $this->tagLine() : $this->dice->pick(self::TEXT);
            $doc .= $eol . $indent . $this->dice->pick([' * ', ' * ', ' *', ' *  ', ' ', '']) . $content;
        }
        return $doc . $eol . $indent . ' */';
    }

    public function mutate(string $doc): string
    {
        return $this->dice->mutate($doc, edits: 2, margin: 3, pool: self::MUTATIONS);
    }

    private function type(int $depth = 0): string
    {
        $roll = $depth > 3 ? 1 : $this->dice->int(1, 100);
        $atom = match (true) {
            $roll <= 40 || $roll > 80 => $this->dice->pick(self::NAMES),
            $roll <= 55 => $this->generic($depth),
            $roll <= 65 => $this->dice->pick(self::STRINGS),
            $roll <= 72 => $this->dice->pick(self::INTEGERS),
            default => $this->dice->pick(self::CLASSES) . '::' . $this->dice->pick(self::CONSTANTS),
        };
        $union = $this->dice->chance(15);
        return $union ? $atom . $this->space() . '|' . $this->space() . $this->type($depth + 1) : $atom;
    }

    private function generic(int $depth): string
    {
        $pair = $this->dice->chance(40);
        $second = $pair ? $this->space() . ',' . $this->dice->pick([' ', '', '  ']) . $this->type($depth + 1) : '';
        return (
            $this->dice->pick(self::GENERICS)
            . '<'
            . $this->space()
            . $this->type($depth + 1)
            . $second
            . $this->space()
            . '>'
        );
    }

    private function space(): string
    {
        return $this->dice->either(20, $this->dice->pick([' ', '  ', "\t"]));
    }

    private function tagLine(): string
    {
        $tag = $this->dice->pick(self::TAGS);
        $unnamed = $this->dice->chance(50) && str_ends_with($tag, 'var');
        $variable = $unnamed ? '' : ' ' . $this->dice->pick(self::VARIABLES);
        $description = $this->dice->either(30, ' ' . $this->dice->pick(self::TEXT));
        $description = $unnamed ? '' : $description;
        return (
            $tag
            . $this->dice->pick([' ', ' ', '  ', "\t"])
            . $this->type()
            . $variable
            . $description
            . $this->dice->either(10, ' ')
        );
    }
}
