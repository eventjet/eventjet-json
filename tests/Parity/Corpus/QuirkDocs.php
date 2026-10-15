<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

/**
 * Builds doc comments where an unknown tag's description spans lines with empty, blank, and text lines, and inline tags
 * follow mid-line. phpdoc-parser keeps a save point of an earlier line in some of these cases.
 *
 * @internal
 */
final readonly class QuirkDocs
{
    private const array LINES = ['', ' ', '  ', "\t", 'text', 'more text', 'x', '*', '* text'];

    private const array INLINE = [
        '@foo',
        '@bar',
        '@\\Baz',
        '@\\Baz(',
        '@\\Baz()',
        '@return int',
        '@return(int)',
        '@param int $x',
        '@param(',
        '@param list<string> $value',
        '@var list<string>',
        '@phpstan-param list<bool> $value',
        '@deprecated',
        '@phpstan-import-type A from B x',
        '@foo(',
        '@foo()',
        '@psalm-type A = (//',
    ];

    private const array STARTS = ['@foo', '@bar text', '@see x', '@\\Baz', '@\\Baz()', '@foo()', '@foo(a=1)'];

    public function __construct(
        private Dice $dice,
    ) {}

    public function doc(): string
    {
        $lines = [$this->dice->either(50, $this->dice->pick(['@param list<int> $value', '@var list<int>']))];
        $lines[] = $this->dice->pick(self::STARTS) . $this->dice->either(33, ' ' . $this->dice->pick(self::INLINE));
        for ($line = $this->dice->int(0, 4); $line > 0; $line--) {
            $lines[] = $this->line();
        }
        $lines[] = $this->dice->either(
            67,
            $this->dice->pick([
                '@param list<int> $value',
                '@var list<int>',
                '@phpstan-param list<string> $value',
            ]),
        );
        $doc = '/**';
        foreach ($lines as $line) {
            $doc .= "\n" . $this->dice->pick([' * ', ' *', ' *  ', '   ', '']) . $line;
        }
        return $doc . "\n */";
    }

    private function line(): string
    {
        $second = $this->dice->either(25, ' ' . $this->dice->pick(self::INLINE));
        $inline = $this->dice->pick([' ', "\t", '']) . $this->dice->pick(self::INLINE) . $second;
        return $this->dice->pick(self::LINES) . $this->dice->either(33, $inline);
    }
}
