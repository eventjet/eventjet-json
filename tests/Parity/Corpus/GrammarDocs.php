<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function implode;
use function str_contains;

/**
 * Builds random doc comments from `@param` and `@var` tags, other tags, Doctrine annotations, and text, with types from
 * {@see GrammarTypes}.
 *
 * @internal
 */
final readonly class GrammarDocs
{
    public const array MUTATIONS = [
        '<',
        '>',
        '{',
        '}',
        '(',
        ')',
        '[',
        ']',
        '|',
        '&',
        ',',
        ':',
        '?',
        '$',
        '@',
        "'",
        '"',
        '\\',
        ' ',
        "\n",
        "\n * ",
        '*',
        '/',
        '=',
        '.',
        '-',
        '_',
        'a',
        '1',
        "\r",
        '#',
    ];

    private const array TAGS = [
        '@param',
        '@param',
        '@param',
        '@phpstan-param',
        '@psalm-param',
        '@phan-param',
        '@var',
        '@var',
        '@phpstan-var',
        '@psalm-var',
        '@phan-var',
    ];

    private const array VARIABLES = ['$value', '$value', '$values', '$other', '$x', '$this', '$Value'];

    private const array TEXT = [
        'Some text.',
        'text @param int $value',
        'Don\'t use "this".',
        'a | b',
        '{@inheritDoc}',
        'https://example.com/a//b',
        'x <p>y</p>',
        '@',
        '42',
        'see @var list<int> $x',
        '`list<int>`',
        '*',
        '* / *',
    ];

    private const array LINE_ENDS = ["\n * ", "\n * ", "\n * ", "\r\n * ", "\n ", "\n\t * ", "\n *  "];

    private GrammarTypes $types;
    private OtherTags $otherTags;

    public function __construct(
        private Dice $dice,
    ) {
        $this->types = new GrammarTypes($dice);
        $this->otherTags = new OtherTags($dice, $this->types);
    }

    public function types(): GrammarTypes
    {
        return $this->types;
    }

    public function doc(): string
    {
        $lines = [];
        for ($line = $this->dice->int(1, 5); $line > 0; $line--) {
            $lines[] = $this->line();
        }
        $oneLine = $this->dice->chance(25);
        if ($oneLine) {
            return '/** ' . implode(' ', $lines) . ' */';
        }
        $eol = $this->dice->pick(self::LINE_ENDS);
        $start = $this->dice->either(20, '/** ', '/**' . $eol);
        $end = $this->dice->pick(["\n */", "\r\n */", ' */', "\n*/", "\n *\n */"]);
        return $start . implode($eol, $lines) . $end;
    }

    /** Returns a doc comment with a single tag or line. */
    public function line(): string
    {
        $roll = $this->dice->int(1, 100);
        return match (true) {
            $roll <= 45 => $this->tag(),
            $roll <= 70 => $this->otherTags->tag(),
            $roll <= 85 => $this->dice->pick(self::TEXT),
            $roll <= 92 => '',
            $roll <= 95 => $this->dice->pick(['@\Foo(', '@\Foo(x)', '@_foo', '@\Foo\Bar(name="x")', '@\Foo({)']),
            default => $this->dice->pick([
                '{@inheritDoc}',
                '@',
                '* @param int $value',
                '  @param int $value',
                "\t@var int",
            ]),
        };
    }

    public function tag(): string
    {
        $tag = $this->dice->pick(self::TAGS);
        $roll = $this->dice->int(1, 100);
        $variable = $this->dice->pick(self::VARIABLES);
        if ($roll <= 6) {
            $variable = $this->dice->pick(['&' . $variable, '...' . $variable, '& ...' . $variable, '']);
        }
        $type = $roll > 6 && $roll <= 10 ? '' : $this->types->type();
        $separator = $this->dice->either(8, $this->dice->pick(['', "\n * ", "\n *     "]), ' ');
        $description = $this->dice->either(35, ' ' . $this->dice->pick(self::TEXT));
        $unnamed = $this->dice->chance(55) && str_contains($tag, 'var');
        if ($unnamed) {
            $variable = '';
            $suffix = $this->dice->pick([' |string', '|string', ' &', ',', ' description', 'x']);
            $description = $this->dice->either(30, $suffix, $description);
        }
        $afterTag = $this->dice->either(4, '', ' ');
        return $tag . $afterTag . $type . $separator . $variable . $description;
    }

    public function otherTag(): string
    {
        return $this->otherTags->tag();
    }
}
