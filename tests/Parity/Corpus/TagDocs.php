<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

/**
 * Builds doc comments from lines that combine tags, inline tags, Doctrine annotations, multi-line descriptions, and
 * comments in types, which decide where tags end in phpdoc-parser.
 *
 * @internal
 */
final readonly class TagDocs
{
    private const array PROBES = [
        '@param list<int> $value',
        '@phpstan-param list<string> $value',
        '@psalm-param list<bool> $value',
        '@var list<int>',
        '@phpstan-var list<string>',
        '@var list<float> $value',
        '@param list<int>',
        '@param',
        '@var',
    ];

    private const array UNKNOWN = [
        '@foo',
        '@bar',
        '@Foo',
        '@see',
        '@internal',
        '@ab:cd',
        '@Foo\\Bar',
        '@x-y',
        '@inheritDoc',
    ];

    private const array DOCTRINE = ['@\\Foo', '@_foo', '@\\Foo\\Bar'];

    private const array KNOWN = [
        '@return',
        '@throws',
        '@deprecated',
        '@template',
        '@extends',
        '@method',
        '@property',
        '@param-out',
        '@phpstan-assert',
        '@mixin',
        '@phpstan-type',
        '@psalm-type',
        '@phpstan-import-type',
        '@psalm-import-type',
        '@param-later-invoked-callable',
        '@phpstan-self-out',
    ];

    private const array VALUES = [
        '',
        'int',
        'int $x',
        '$x',
        'Foo from Bar',
        'Foo from Bar as Baz',
        'Foo from Bar trailing',
        'Foo = int',
        'Foo int|',
        'Foo = list<',
        'T of int',
        'Foo<int>',
        'static int foo()',
        'int foo(int $a = 1)',
        '!int $x',
        '(int)',
        '(',
        ')',
        'callable(): Foo',
        "list<int // c\n * >",
        "Foo = array{ // c\n * int, !}",
        "Foo = (int // c\n * |",
        'text',
        'text (x)',
        '"q"',
        '{a}',
        '[1]',
    ];

    private const array ARGUMENTS = [
        '()',
        '(1)',
        '(a=1, b="x")',
        '({1, "a": 2})',
        '(@Bar)',
        '(',
        '(1',
        '(a=)',
        "('x')",
        "(\n * a=1,\n * b=2\n * )",
        "(\n * a=1,",
        "(\"multi\n * line\")",
        '(@Bar(1), {x = @Baz})',
        '(true::X)',
    ];

    private const array TEXT = [
        'Text.',
        'More text',
        '',
        '',
        '(paren)',
        '{@inheritDoc}',
        'a | b',
        '| x',
        '& y',
        '// c',
        '"open',
        "'q'",
        'x */y',
        '<b>bold</b>',
        '*',
        '**',
    ];

    public function __construct(
        private Dice $dice,
    ) {}

    public function doc(): string
    {
        $eol = $this->dice->either(90, "\n", "\r\n");
        $doc = '/**';
        for ($line = $this->dice->int(1, 6); $line > 0; $line--) {
            $doc .= $eol . $this->dice->pick([' * ', ' * ', ' *', ' *  ', '   ', '']) . $this->line();
        }
        $doc .= $this->dice->either(70, $eol . ' * ' . $this->dice->pick(self::PROBES));
        return $doc . $eol . ' */';
    }

    private function line(): string
    {
        $line = $this->segment();
        for ($more = $this->dice->chance(35); $more; $more = $this->dice->chance(35)) {
            $line .= $this->dice->pick([' ', '', '  ', "\t"]) . $this->segment();
        }
        return $line;
    }

    private function segment(): string
    {
        $kind = $this->dice->int(1, 7);
        return match ($kind) {
            1 => $this->dice->pick(self::PROBES),
            2 => $this->dice->pick(self::UNKNOWN) . $this->dice->either(30, $this->dice->pick(self::ARGUMENTS)),
            3 => $this->dice->pick(self::DOCTRINE) . $this->dice->either(60, $this->dice->pick(self::ARGUMENTS)),
            4, 5 => $this->dice->pick(self::KNOWN) . ' ' . $this->dice->pick(self::VALUES),
            default => $this->dice->pick(self::TEXT),
        };
    }
}
