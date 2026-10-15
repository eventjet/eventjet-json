<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function str_contains;
use function str_replace;
use function strlen;
use function substr;

/**
 * Builds random doc comments that probe how phpdoc-parser ends a tag, like {@see ProbeDocs}: comments in types, lone
 * carriage returns, multi-line annotations, and random tags behind the type alias.
 *
 * @internal
 */
final readonly class RandomProbeDocs
{
    private const array COMMENT_TAGS = [
        '@return',
        '@throws',
        '@property',
        '@param-out',
        '@mixin',
        '@phpstan-self-out',
        '@template T of',
        '@template T =',
        '@extends Foo<',
        '@implements',
        '@phpstan-assert',
        '@method',
        '@method static',
        '@method int foo(',
        '@psalm-type B =',
        '@param-closure-this',
        '@phpstan-require-extends',
        '@var',
        '@param',
        '@phpstan-param',
        '@psalm-var',
    ];

    private const array CARRIAGE_RETURN_PARTS = [
        "\r",
        "\n",
        ' * ',
        '@param list<int> $value',
        '@var list<int>',
        ' ',
        '*/',
        'text',
        '@psalm-type A = (//',
        '!',
        "list<int|\r>",
        "@method foo(\r)",
        "@Foo(\r1)",
        '(',
        '<',
        "\r\n",
        '@param list<int> $value @Foo(1,',
        '@var(int)',
        ')',
    ];

    private const array TEXT = [
        'text',
        '',
        ' ',
        '@foo',
        '@foo text',
        '@foo text @Bar(1,',
        'more @Bar(1,',
        '@var(int)',
        ')',
        '@var(int))',
        'text @param(',
        '@foo @bar',
        '@foo text @bar text',
        '@foo text @param(x)',
        '@Bar(1,',
        '@var int',
        '@foo text @var(int)',
        '@foo text @Bar',
        'x @Bar(',
        '1)',
        '@psalm-foo text @Bar(1,',
        '@foo text @param int $x',
        '@param int $x text',
        '@param int $x',
        '@var int text',
        '@var int $x text @Bar(1,',
        '@param int $x @Bar(1,',
        '@foo @Bar(1,',
        '@foo text @Bar(1)',
        '@Bar(1) text',
        '@Bar(1) @Baz(2,',
        '@foo text @param(',
        'text @foo(',
        '@method foo() text @Bar(1,',
        '@psalm-type B = int',
        '@psalm-type B = int @Bar(1,',
        '@template T text @Bar(1,',
        '@throws X text @Bar(1,',
        '@return int text @Bar(1,',
        '@foo text',
        '@foo text @bar(',
        '@foo text @bar() @Bar(1,',
        '@foo text @var(',
        '@foo text @var() x',
        "\t",
        '*',
        '* text',
        '@foo text @Bar(1,)',
        '@foo text @Bar(@Baz(1,',
        '@deprecated text @Bar(1,',
        '@see X text @Bar(1,',
        '@var int @Bar(1,',
    ];

    public function __construct(
        private Dice $dice,
        private GrammarDocs $grammar,
    ) {}

    /**
     * Types with comments before line breaks, cut and used in a failing type alias after a `@param` tag, and complete in
     * a tag after an alias that leaves a comment behind. Any later complete type clears the comment.
     *
     * @return iterable<ParityInput>
     */
    public function comments(): iterable
    {
        $type = str_replace(search: "\n", replace: " // c\n", subject: $this->grammar->types()->type());
        if (!str_contains($type, '//')) {
            $type = '(' . $type . " // c\n * )";
        }
        for ($cut = 0; $cut < 8; $cut++) {
            $prefix = substr($type, offset: 0, length: $this->dice->int(1, strlen($type)));
            foreach (['', '|', ' !', ')'] as $suffix) {
                yield ParityInput::doc(
                    "/**\n * @param list<int> \$value\n * @psalm-type A = " . $prefix . $suffix . "\n */",
                );
            }
        }
        $head = "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * ";
        $tag = $this->dice->pick(self::COMMENT_TAGS);
        foreach (['', ' $x', ' $x desc', ' desc', '|', ')'] as $suffix) {
            yield ParityInput::doc($head . $tag . ' ' . $type . $suffix . "\n */");
            yield ParityInput::doc($head . $tag . ' ' . $this->grammar->types()->type() . $suffix . "\n */");
        }
        yield ParityInput::doc($head . $this->grammar->otherTag() . "\n */");
        yield ParityInput::doc(str_replace(search: "\n", replace: " // c\n", subject: $this->grammar->doc()));
    }

    /** Returns a doc comment with lone carriage returns, where the lexer stops early. */
    public function carriageReturns(): string
    {
        $doc = '/**' . $this->dice->either(50, ' ', "\n");
        for ($part = $this->dice->int(1, 8); $part > 0; $part--) {
            $doc .= $this->dice->pick(self::CARRIAGE_RETURN_PARTS);
        }
        return $doc . $this->dice->either(50, "\r*/", "\n */");
    }

    /**
     * Returns lines of descriptions, inline tags, and multi-line annotations, mostly behind the type alias. An annotation
     * that swallows the `@var(int)` line keeps the doc comment unreadable.
     */
    public function text(): string
    {
        $body = '';
        for ($line = $this->dice->int(1, 5); $line > 0; $line--) {
            $body .= $this->dice->either(17, "\n", "\n * ") . $this->dice->pick(self::TEXT);
        }
        $head = $this->dice->either(
            25,
            "/**\n * @param list<int> \$value",
            "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !",
        );
        return $head . $body . $this->dice->either(20, ' */', "\n */");
    }

    /** Returns a random tag or line behind the type alias. */
    public function tag(int $index): string
    {
        $line = match ($index % 3) {
            0 => $this->grammar->otherTag(),
            1 => $this->grammar->tag(),
            default => $this->grammar->line(),
        };
        return ProbeDocs::HEAD . $line . "\n */";
    }
}
