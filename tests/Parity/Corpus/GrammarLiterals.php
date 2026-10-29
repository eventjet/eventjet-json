<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

/**
 * Builds number and string literals and constants for {@see GrammarTypes}, including the spellings that PHP and PHPStan
 * read differently and invalid ones.
 *
 * @internal
 */
final readonly class GrammarLiterals
{
    private const array INTEGERS = [
        '0',
        '42',
        '-1',
        '+5',
        '0x2A',
        '0X2a',
        '0b101',
        '0o17',
        '017',
        '1_000',
        '1__0',
        '1_',
        '9223372036854775807',
        '9223372036854775808',
        '-9223372036854775808',
        '-9223372036854775809',
        '0x',
        '00',
        '-0',
        '0_1',
    ];

    private const array FLOATS = [
        '1.5',
        '.5',
        '1.',
        '-1.5e2',
        '1e3',
        '1E3',
        '1_2.5e+1',
        '1e999',
        '-1e999',
        '3.0',
        '1.5.5',
        '1e',
        '0.1e-5',
        '00.5',
        '+.5e-3',
    ];

    private const array STRING_PARTS = [
        'a',
        'foo',
        ' ',
        '|',
        ',',
        '<',
        '>',
        '{',
        '}',
        '@var',
        '@',
        '$x',
        '\\n',
        '\\\\',
        "\\'",
        '\\"',
        '\\$',
        '\\x41',
        '\\xff',
        '\\101',
        '\\0',
        '\\e',
        '\\q',
        '\\u{1f600}',
        '\\u{e9}',
        '\\u{7ff}',
        '\\u{d800}',
        '\\u{110000}',
        '\\u{ffffffff}',
        '\\u{ffffffffffffffffffffffff}',
        '\\u{}',
        '\\',
        "'",
        '"',
        "\n",
        'ü',
        '*',
        '//',
    ];

    private const array CONSTANTS = [
        'Foo::BAR',
        'self::BAR_*',
        'self::*',
        'Foo::*',
        '\Foo\Bar::class',
        'static::X',
        'parent::X',
        'Foo :: BAR',
        'Foo:: BAR',
        'Foo ::BAR',
        'true::X',
        'null::X',
        'array::X',
        'PHP_INT_MAX',
        '\PHP_INT_MIN',
        'self::A*B',
        'self::*_X',
        'self::A* B',
        'Foo::',
        'self::BAR-BAZ',
        'Foo::bar::baz',
    ];

    private const array HTML = ['<p>', 'Foo<p>', 'list<int></int>', 'Foo<b>x</b>', 'a</p>'];

    private const array PUNCTUATION = [
        '#',
        '%',
        '@x',
        '=>',
        '->',
        '!',
        '=',
        '...',
        '*',
        '//',
        '\\',
        '{',
        '}',
        ']',
        '>',
        ',',
        ':',
    ];

    public function __construct(
        private Dice $dice,
    ) {}

    private function literal(): string
    {
        $roll = $this->dice->int(1, 100);
        if ($roll <= 35) {
            return $this->dice->pick(self::INTEGERS);
        }
        if ($roll <= 50) {
            return $this->dice->pick(self::FLOATS);
        }
        $quote = $this->dice->either(50, "'", '"');
        $content = '';
        for ($part = $this->dice->int(0, 4); $part > 0; $part--) {
            $content .= $this->dice->pick(self::STRING_PARTS);
        }
        return $quote . $content . $this->dice->either(97, $quote);
    }

    /** Returns a literal, a constant, `$this`, HTML, or punctuation for a roll from 76 to 100. */
    public function atom(int $roll): string
    {
        return match (true) {
            $roll <= 86 => $this->literal(),
            $roll <= 93 => $this->dice->pick(self::CONSTANTS),
            $roll <= 95 => '$this',
            $roll <= 97 => $this->dice->pick(self::HTML),
            default => $this->dice->pick(self::PUNCTUATION),
        };
    }
}
